#!/usr/bin/env python
"""decider_probe.py — resolve the generation-dedup plan's load-bearing unknowns,
navigating the tree properly (carcode page -> group href -> parttype jsn -> fragment)."""
import os, re, sys, json, html as H, statistics
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
os.environ["EVOMI_COUNTRY"] = "US"
import parsers
from ra_client import RAClient, CATALOG_API
from proxy_manager import EvomiProxyManager
import urllib.parse
c = RAClient(EvomiProxyManager())

def frag_listings(jsn, mgi):
    body={"func":"navnode_fetch","payload":json.dumps({"jsn":jsn,"max_group_index":int(mgi)},separators=(",",":")),
          "api_json_request":"1","sctchecked":"1","scbeenloaded":"false","curCartGroupID":""}
    if c._nck: body["_nck"]=c._nck; body["_jnck"]=urllib.parse.quote(c._nck,safe="")
    d=c._send("POST",CATALOG_API,data=body,headers=c._api_headers()).json()
    frag=next((v for v in (d.get("html_fill_sections") or {}).values() if isinstance(v,str)), None)
    return frag or ""

def brakepad_parts(make, year, model):
    """Navigate to the disc-brake-pad (parttype 1684) leaf for a vehicle, return {(brand,pn):price}."""
    v = c.get(f"/en/catalog/{make},{year},{model}")
    ccs=[n for n in parsers.parse_nav(v) if n.get("nodetype")=="carcode"]
    if not ccs: return None, None
    cc = ccs[0]["carcode"]
    cp = c.get(f"/en/catalog/{make},{year},{model},{cc}")
    m=re.search(r'id="max_group_index"[^>]*value="(\d+)"',cp)
    mgi=int(m.group(1)) if m else 0
    groups=[g for g in parsers.parse_nav(cp) if g.get("nodetype")=="groupname" and g.get("href")]
    brake=[g for g in groups if "brake" in (g.get("label","")+g.get("groupname","")).lower()]
    if not brake: return cc, None
    gp=c.get(brake[0]["href"])
    jsns={}
    for mm in re.finditer(r'id="jsn\[(\d+)\]"\s+value="([^"]*)"', gp):
        try:
            j=json.loads(H.unescape(mm.group(2)))
            if j.get("nodetype")=="parttype": jsns[str(j.get("parttype"))]=j
        except: pass
    pt = jsns.get("1684") or (list(jsns.values())[0] if jsns else None)
    if not pt: return cc, None
    frag=frag_listings(pt, mgi)
    parts={(l.get("brand_name"),l.get("part_number")):l.get("price")
           for l in parsers.parse_listings(frag,{}) if l.get("part_number")}
    return cc, parts

print("=== TEST 2: generation-dedup (Honda Accord disc brake pad, 2013-2017) ===")
psets={}
for y in [2013,2015,2017]:
    try:
        cc,pp=brakepad_parts("honda",y,"accord")
        if pp is not None:
            psets[y]=pp; print(f"  {y} cc={cc}: {len(pp)} pad parts")
        else: print(f"  {y} cc={cc}: no leaf")
    except Exception as e: print(f"  {y}: err {type(e).__name__}: {e}")
if len(psets)>=2:
    yrs=sorted(psets); union=set().union(*[set(p) for p in psets.values()])
    inter=set(psets[yrs[0]])
    for y in yrs: inter&=set(psets[y])
    print(f"  union={len(union)} intersection={len(inter)}  1-year-captures={100*len(psets[yrs[0]])/max(1,len(union)):.0f}% of union")
    pchg=sum(1 for k in inter if len({psets[y][k] for y in yrs if psets[y].get(k) is not None})>1)
    print(f"  price drift: {pchg}/{len(inter)} shared parts change price across years")

print("\n=== TEST 1+3: getbuyersguide validity + fan-out ===")
v=c.get("/en/catalog/honda,2015,accord")
cc=[n for n in parsers.parse_nav(v) if n.get("nodetype")=="carcode"][0]["carcode"]
cp=c.get(f"/en/catalog/honda,2015,accord,{cc}")
groups=[g for g in parsers.parse_nav(cp) if g.get("nodetype")=="groupname" and g.get("href") and "brake" in (g.get("label","")+g.get("groupname","")).lower()]
gp=c.get(groups[0]["href"])
# get real partData (listing_data_essential) blobs from the group page's leaf
blobs=[]
for mm in re.finditer(r'id="listing_data_essential\[\d+\]"\s+value="([^"]*)"', gp):
    try: blobs.append(json.loads(H.unescape(mm.group(1))))
    except: pass
print(f"listing_data_essential blobs on group page: {len(blobs)}")
fan=[]
for pd in blobs[:6]:
    for shape in ({"partData":pd}, pd):
        body={"func":"getbuyersguide","payload":json.dumps(shape,separators=(",",":")),
              "api_json_request":"1","sctchecked":"1","scbeenloaded":"false","curCartGroupID":""}
        if c._nck: body["_nck"]=c._nck; body["_jnck"]=urllib.parse.quote(c._nck,safe="")
        txt=c._send("POST",CATALOG_API,data=body,headers=c._api_headers()).text
        veh=len(re.findall(r'\bcarcode\b', txt))
        if "No applications found" not in txt and veh:
            fan.append(veh); print(f"  pk={pd.get('partkey')}: WORKS shape={'wrap' if 'partData' in shape else 'raw'} vehicles~={veh} chars={len(txt)}"); break
    else:
        print(f"  pk={pd.get('partkey')}: no fitment (chars~{len(txt)})")
if fan:
    F=statistics.mean(fan)
    print(f"  >>> getbuyersguide WORKS, F~={F:.0f} veh/part -> U~={91e6/F/1e6:.1f}M unique parts")
else:
    print("  >>> getbuyersguide BROKEN via listing_data_essential -> fitment needs another source")
