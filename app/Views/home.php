<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Supreme Autos — Quality Vehicle Parts</title>
<meta name="description" content="Find the right replacement and performance parts for your exact vehicle. Search by vehicle or VIN.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://images.unsplash.com" crossorigin>
<link rel="preconnect" href="https://cdn.simpleicons.org" crossorigin>
<link rel="preconnect" href="https://flagcdn.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap"></noscript>
<?php $cssv = @filemtime(dirname(__DIR__, 2) . '/assets/css/app.css') ?: date('YmdHis'); ?>
<link rel="stylesheet" href="<?= e($base) ?>/assets/css/app.css?v=<?= $cssv ?>">
<link rel="icon" href="<?= e($base) ?>/assets/img/favicon.png" type="image/png">
<link rel="apple-touch-icon" href="<?= e($base) ?>/assets/img/apple-touch-icon.png">
<style>
/* ===== Homepage sections — BMW-M (base/header/footer come from app.css) ===== */
.sec{padding:var(--sec-pad) 0}
.sec-head{max-width:720px;margin-bottom:var(--head-gap)}
.sec-head .eyebrow{margin-bottom:20px}
.sec-head h2{font-size:clamp(24px,3.2vw,36px);text-transform:uppercase;letter-spacing:-.01em;line-height:1;margin:0;color:var(--ink);font-weight:700}
.sec-head p{color:var(--body);font-size:16px;font-weight:300;line-height:1.5;margin-top:16px;max-width:56ch}
.eyebrow{display:inline-flex;align-items:center;gap:12px;font-size:14px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--body-strong)}
.eyebrow::before{content:"";width:28px;height:4px;background:var(--m-stripe)}
.eyebrow.on-navy{color:var(--body-strong)}
.hl{color:#fff}
.reveal{opacity:0;transform:translateY(24px);transition:opacity .8s var(--ease-out),transform .8s var(--ease-out)}
.reveal.in{opacity:1;transform:none}
@media (prefers-reduced-motion:reduce){.reveal{opacity:1;transform:none;transition:none}*{scroll-behavior:auto!important}}

/* homepage button variants map onto the BMW-M flat/uppercase language */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;height:48px;padding:0 30px;border:1px solid var(--hairline);background:transparent;color:var(--ink);font-family:var(--f);font-weight:700;font-size:14px;letter-spacing:1.5px;text-transform:uppercase;cursor:pointer;transition:background .2s,border-color .2s,color .2s}
.btn:hover{background:var(--ink);border-color:var(--ink);color:var(--canvas)}
.btn:active{transform:translateY(1px)}
.btn .ar{display:grid;place-items:center;width:20px;height:20px}
.btn .ar svg{width:15px;height:15px}
.btn-red{background:var(--ink);border-color:var(--ink);color:var(--canvas)}
.btn-red:hover{background:transparent;color:var(--ink)}
.btn-dark,.btn-navy{background:transparent;border-color:var(--hairline);color:var(--ink)}
.btn-dark:hover,.btn-navy:hover{background:var(--ink);border-color:var(--ink);color:var(--canvas)}
.btn-outline{background:transparent;border-color:var(--hairline);color:var(--ink)}
.btn-outline:hover{background:var(--ink);border-color:var(--ink);color:var(--canvas)}
.btn-ghost-light{background:transparent;border-color:rgba(255,255,255,.5);color:#fff}
.btn-ghost-light:hover{background:#fff;border-color:#fff;color:#000}
.btn-block{display:flex;width:100%}
.help-link{font-size:14px;color:var(--ink);font-weight:700;text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--hairline)}
.help-link:hover{border-color:var(--ink)}

/* form controls (search card) */
.field label,.fld>label{display:block;font-size:14px;font-weight:700;color:var(--body-strong);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
.control{width:100%;height:48px;border:1px solid var(--hairline);background:var(--surface-card);color:var(--ink);padding:0 16px;font-family:var(--f);font-size:14px;font-weight:300;transition:border-color .2s}
.control:focus{outline:none;border-color:var(--ink)}
.control:disabled{background:var(--surface-soft);color:var(--mute);cursor:not-allowed}
select.control{appearance:none;background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='none' stroke='%237e7e7e' stroke-width='1.8' viewBox='0 0 24 24'><path d='m6 9 6 6 6-6'/></svg>");background-repeat:no-repeat;background-position:right 15px center;padding-right:40px}

/* ===== HERO — full-bleed automotive photo band ===== */
.hero{position:relative;background:#000;overflow:hidden;isolation:isolate;border-bottom:1px solid var(--hairline)}
.hero::before{content:"";position:absolute;inset:0;z-index:0;background:linear-gradient(90deg,rgba(0,0,0,.92) 0%,rgba(0,0,0,.72) 45%,rgba(0,0,0,.45) 100%),url("https://images.unsplash.com/photo-1503376780353-7e6692767b70?q=80&w=1920&auto=format&fit=crop") center/cover no-repeat}
.hero .wrap{position:relative;z-index:2;text-align:center;padding:104px 40px 150px}
.hero-copy{max-width:800px;margin:0 auto}
.hero-copy h1{color:#fff;font-size:clamp(26px,4vw,38px);font-weight:700;text-transform:uppercase;letter-spacing:-.01em;line-height:1.05;margin:22px 0}
.hero-copy p{color:rgba(255,255,255,.82);font-size:17px;font-weight:300;line-height:1.5;max-width:54ch;margin:0 auto 32px}
.hero-cta{display:flex;gap:14px;flex-wrap:wrap;justify-content:center}
.hero-stats{display:flex;margin-top:44px;flex-wrap:wrap;justify-content:center}

/* ===== FIND YOUR PARTS BAR (overlaps the hero bottom) ===== */
.findbar-wrap{position:relative;z-index:5;margin-top:-96px}
.findbar{background:var(--canvas);border:1px solid var(--hairline);padding:22px 24px 20px}
.fb-top{display:flex;align-items:center;gap:14px;padding-bottom:16px;border-bottom:1px solid var(--hairline);margin-bottom:18px}
.fb-mark{color:var(--ink);display:grid;place-items:center;flex:0 0 auto}
.fb-lead{display:flex;flex-direction:column;line-height:1.25}
.fb-count{font-size:14px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:var(--mute)}
.fb-lead b{font-size:17px;font-weight:700;text-transform:uppercase;letter-spacing:.02em;color:var(--ink)}
.fb-top .tabs{margin-left:auto;border-bottom:0}
.fb-top .tab{flex:0 0 auto;height:auto;padding:8px 14px}
.fb-row{display:grid;grid-template-columns:repeat(4,1fr) auto;gap:12px;align-items:center}
.fb-row .control{height:48px}
.fb-row .btn{height:48px;padding:0 24px;white-space:nowrap}
.fb-row-vin{grid-template-columns:1fr auto}
.fb-body .msg{margin-top:12px}
.fb-body .recent{margin:14px 0 0}
.fb-body .vinnote{margin-top:12px}
.hero-stats>div{padding:2px 32px}
.hero-stats>div:first-child{padding-left:0}
.hero-stats>div+div{border-left:1px solid var(--hairline)}
.hero-stats b{display:block;font-family:var(--f-mono);font-size:28px;font-weight:600;color:#fff;letter-spacing:-.02em;line-height:1.1}
.hero-stats span{font-size:14px;color:var(--body);font-weight:300;text-transform:uppercase;letter-spacing:.06em}
/* search card */
.searchshell{background:var(--surface-elevated);border:1px solid var(--hairline)}
.searchcard{background:var(--surface-card)}
.tabs{display:flex;border-bottom:1px solid var(--hairline)}
.tab{flex:1;position:relative;height:56px;font-family:var(--f);font-weight:700;font-size:14px;letter-spacing:1px;text-transform:uppercase;color:var(--mute);display:flex;align-items:center;justify-content:center;gap:8px;background:none;border:0;cursor:pointer;transition:color .2s}
.tab.on{color:var(--ink)}
.tab.on::after{content:"";position:absolute;left:0;right:0;bottom:-1px;height:2px;background:#fff}
.tab:hover:not(.on){color:var(--body)}
.tabbody{padding:24px}
.tabhead{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px}
.tabhead h3{font-size:15px;font-weight:700;text-transform:uppercase;letter-spacing:.02em;color:var(--ink)}
.tabhead .live{display:inline-flex;align-items:center;gap:6px;font-size:14px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--success)}
.tabhead .live .d{width:7px;height:7px;border-radius:50%;background:var(--success)}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}
.fld{position:relative}
.recent{display:flex;align-items:center;gap:12px;background:var(--surface-soft);padding:12px 14px;margin-bottom:16px;font-size:14px;color:var(--body)}
.recent .ic{width:30px;height:22px;background:var(--surface-elevated);display:grid;place-items:center;color:var(--ink);flex:0 0 auto}
.recent b{font-weight:700;color:var(--ink)}.recent .go{margin-left:auto;color:var(--ink);font-weight:700;text-transform:uppercase;font-size:14px;letter-spacing:1px}
.savegarage{display:flex;align-items:center;gap:10px;font-size:14px;color:var(--body);margin-top:14px;cursor:pointer}
.savegarage input{width:16px;height:16px;accent-color:var(--m-red)}
.vinnote{display:flex;align-items:flex-start;gap:10px;background:var(--surface-soft);padding:14px;font-size:14px;color:var(--body);margin-top:14px;line-height:1.5}
.vinnote svg{flex:0 0 auto;margin-top:1px;color:var(--ink)}
.msg{font-size:14px;font-weight:600;margin-top:12px;display:none;align-items:center;gap:7px}
.msg.err{color:var(--m-red);display:flex}
.msg.ok{color:var(--success);display:flex}

/* ===== TRUST STRIP ===== */
.trust{border-bottom:1px solid var(--hairline);background:var(--canvas)}
.trust .wrap{display:grid;grid-template-columns:repeat(4,1fr);gap:0}
.trust .b{display:flex;align-items:flex-start;gap:16px;padding:36px 32px;border-right:1px solid var(--hairline)}
.trust .b:last-child{border-right:0}
.trust .b .ic{width:44px;height:44px;background:var(--surface-card);border:1px solid var(--hairline);display:grid;place-items:center;color:var(--ink);flex:0 0 auto}
.trust .b b{display:block;font-size:15px;font-weight:700;color:var(--ink);text-transform:uppercase;letter-spacing:.02em;margin-bottom:4px}
.trust .b span{font-size:14px;color:var(--body);font-weight:300;line-height:1.4;display:block;max-width:24ch}

/* ===== CATALOG ===== */
.catalog{background:var(--surface-soft)}
/* heading and its subtitle stack — the subtitle sits below, not off to the right */
.cat-search{position:relative;width:400px;flex:0 0 auto;margin:0}
.cat-search .control{height:52px;padding-left:46px}
.cat-search svg{position:absolute;left:16px;top:18px;color:var(--mute)}
.catpanel{position:relative;margin-top:32px;background:var(--canvas);border:1px solid var(--hairline)}
.breadcrumb{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:16px 20px;border-bottom:1px solid var(--hairline);background:var(--surface-soft)}
.breadcrumb .crumbs{display:flex;align-items:center;gap:10px;flex-wrap:wrap;font-size:14px;font-weight:700;color:var(--ink);text-transform:uppercase;letter-spacing:.06em}
.breadcrumb .crumbs .ph{color:var(--mute);font-weight:400}
.breadcrumb .crumbs .sep{color:var(--mute)}
.breadcrumb .sp{flex:1}
.breadcrumb .act{display:flex;gap:10px;align-items:center}
.breadcrumb .txtbtn{font-size:14px;font-weight:700;color:var(--mute);text-transform:uppercase;letter-spacing:1px;padding:8px 10px;background:none;border:0;cursor:pointer;transition:color .2s}
.breadcrumb .txtbtn:hover{color:var(--ink)}
.breadcrumb .btn{height:40px;padding:0 18px;font-size:14px}
.cols{display:grid;grid-template-columns:repeat(6,1fr)}
.col{border-right:1px solid var(--hairline);min-width:0;display:flex;flex-direction:column}
.col:last-child{border-right:0}
.col .ch{padding:16px 16px 10px;font-size:14px;letter-spacing:1.2px;text-transform:uppercase;color:var(--mute);font-weight:700;display:flex;align-items:center;justify-content:space-between}
.col .ch .n{color:var(--mute);font-family:var(--f-mono);font-size:14px}
.col .filt{position:relative;padding:0 12px 10px}
.col .filt input{width:100%;height:36px;border:1px solid var(--hairline);padding:0 12px 0 32px;font-size:14px;font-family:var(--f);background:var(--surface-card);color:var(--ink)}
.col .filt input:focus{outline:none;border-color:var(--ink)}
.col .filt svg{position:absolute;left:24px;top:10px;color:var(--mute)}
.col .list{overflow-y:auto;max-height:340px;padding:2px 8px 10px}
.col .list::-webkit-scrollbar{width:8px}.col .list::-webkit-scrollbar-thumb{background:var(--hairline)}
.opt{position:relative;display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px 12px;font-size:14px;font-weight:300;color:var(--body);cursor:pointer;transition:background .15s,color .15s}
.opt:hover{background:var(--surface-card);color:var(--ink)}
.opt .n{font-family:var(--f-mono);font-size:14px;color:var(--mute)}
.opt .cv{color:var(--mute);flex:0 0 auto}
.opt .opt-l{display:inline-flex;align-items:center;gap:7px;min-width:0}
.opt .flags{display:inline-flex;align-items:center;gap:3px;flex:0 0 auto}
.opt .flag{height:11px;width:auto;display:block}
.opt.on{background:var(--surface-card);color:var(--ink);font-weight:600}
.opt.on::before{content:"";position:absolute;left:0;top:6px;bottom:6px;width:3px;background:var(--m-stripe)}
.popular{padding:18px 20px;border-top:1px solid var(--hairline);display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.popular .lbl{font-size:14px;font-weight:700;color:var(--mute);text-transform:uppercase;letter-spacing:1px}
.chipv{display:inline-flex;align-items:center;gap:8px;height:38px;padding:0 14px;border:1px solid var(--hairline);font-size:14px;font-weight:400;color:var(--ink);background:transparent;cursor:pointer;transition:border-color .2s,background .2s}
.chipv:hover{border-color:var(--ink);background:var(--surface-card)}
.chipv.on{border-color:#fff;background:var(--surface-card);color:#fff}
.chipv.on svg{color:#fff}
.chipv svg{color:var(--mute)}
.mcat{display:none;margin-top:24px}
.mstep{background:var(--canvas);border:1px solid var(--hairline);margin-bottom:10px;overflow:hidden}
.mstep .mh{display:flex;align-items:center;gap:12px;padding:16px;width:100%;text-align:left;font-family:var(--f);font-weight:700;font-size:14px;text-transform:uppercase;letter-spacing:.04em;color:var(--ink);background:none;border:0;cursor:pointer}
.mstep .mh .num{width:26px;height:26px;background:var(--surface-card);color:var(--ink);font-size:14px;font-weight:700;font-family:var(--f-mono);display:grid;place-items:center;flex:0 0 auto}
.mstep.done .mh .num{background:#fff;color:#000}
.mstep .mh .val{margin-left:auto;font-size:14px;color:var(--mute);font-weight:400}
.mstep .mh .cv{transition:transform .3s var(--ease)}
.mstep.open .mh .cv{transform:rotate(180deg)}
.mstep .mb{max-height:0;overflow:hidden;transition:max-height .35s var(--ease)}
.mstep.open .mb{max-height:340px;overflow-y:auto}
.mstep .mb .opt{margin:0 12px}
.mstep[aria-disabled="true"]{opacity:.5}

/* ===== BRAND MARQUEE ===== */
.brands{padding:var(--sec-pad) 0;background:var(--canvas);border-bottom:1px solid var(--hairline)}
.brands h2{text-align:center;font-family:var(--f);font-size:14px;font-weight:700;color:var(--mute);letter-spacing:1.5px;text-transform:uppercase;margin-bottom:36px}
.marquee{position:relative;overflow:hidden;-webkit-mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent);mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent)}
.marquee .track{display:flex;gap:64px;width:max-content;animation:scroll 42s linear infinite}
.marquee:hover .track{animation-play-state:paused}
.marquee .lg{height:32px;display:grid;place-items:center;flex:0 0 auto}
.marquee .lg img{height:28px;width:auto;opacity:.55;transition:opacity .3s}
.marquee .lg:hover img{opacity:1}
@keyframes scroll{to{transform:translateX(calc(-50% - 32px))}}
@media (prefers-reduced-motion:reduce){.marquee .track{animation:none;flex-wrap:wrap;justify-content:center;width:auto;gap:40px 56px}.marquee{-webkit-mask-image:none;mask-image:none}}

/* ===== CATEGORIES ===== */
.cats{background:var(--canvas)}
.cats .sec-head{display:flex;justify-content:space-between;align-items:flex-end;max-width:none;gap:24px}
.cats .sec-head .l{max-width:600px}
.catgrid{display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:var(--hairline);border:1px solid var(--hairline)}
.ccard{position:relative;background:var(--canvas);overflow:hidden;display:flex;flex-direction:column;transition:background .3s}
.ccard:hover{background:var(--surface-card)}
.ccard .im{position:relative;aspect-ratio:5/4;background:#fff;display:grid;place-items:center;padding:24px}
.ccard .im img{max-width:82%;max-height:82%;object-fit:contain;transition:transform .4s var(--ease)}
.ccard:hover .im img{transform:scale(1.05)}
.ccard .bd{padding:18px 20px;display:flex;align-items:center;gap:12px}
.ccard .bd .t{flex:1;min-width:0}
.ccard .bd b{display:block;font-size:15px;font-weight:700;color:var(--ink);text-transform:uppercase;letter-spacing:.02em}
.ccard .bd span{font-family:var(--f-mono);font-size:14px;color:var(--mute)}
.ccard .arw{width:38px;height:38px;border:1px solid var(--hairline);display:grid;place-items:center;color:var(--ink);flex:0 0 auto;transition:.25s var(--ease)}
.ccard:hover .arw{background:#fff;border-color:#fff;color:#000}
.ccard.feat{grid-column:span 2;grid-row:span 2}
.ccard.feat .im{aspect-ratio:auto;flex:1;padding:40px}
.ccard.feat .bd b{font-size:20px}
.ccard.feat::before{content:"Featured";position:absolute;top:0;left:0;z-index:2;font-family:var(--f);font-size:14px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#fff;background:var(--m-stripe);padding:6px 14px}
.ccard.wide{grid-column:span 2;flex-direction:row}
.ccard.wide .im{aspect-ratio:auto;width:170px;flex:0 0 auto}
.ccard.wide .bd{flex:1}
.cats-cta{text-align:center;margin-top:40px}

/* ===== FEATURED PARTS ===== */
.featured{background:var(--canvas)}
.pgrid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px}
.pcard{display:flex;flex-direction:column;background:var(--canvas);border:1px solid var(--hairline);transition:background .25s}
.pcard:hover{background:var(--surface-soft)}
.pcard-im{position:relative;display:grid;place-items:center;aspect-ratio:4/3;background:#fff;padding:22px;overflow:hidden}
.pcard-im::before{content:"";position:absolute;inset:0;z-index:0;background:var(--surface-soft) url(<?= e($base) ?>/assets/img/site-logo.png) center/42% no-repeat;opacity:.4}
.pcard-im img{position:relative;z-index:1}
.pcard-im img{max-width:82%;max-height:82%;object-fit:contain;transition:transform .4s var(--ease)}
.pcard:hover .pcard-im img{transform:scale(1.05)}
.pcard-bd{display:flex;flex-direction:column;gap:8px;padding:18px 20px 22px}
.pcard-title{font-size:14px;font-weight:700;color:var(--ink);text-transform:uppercase;letter-spacing:.02em;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.pcard-price{font-family:var(--f-mono);font-size:17px;font-weight:700;color:var(--ink)}

/* ===== PROMO BAND ===== */
.promo{position:relative;background:var(--surface-soft);border-top:1px solid var(--hairline);border-bottom:1px solid var(--hairline);overflow:hidden;isolation:isolate}
.promo::before{content:"";position:absolute;inset:0;z-index:0;background:linear-gradient(rgba(245,245,244,.92),rgba(245,245,244,.84)),url("https://images.unsplash.com/photo-1486262715619-67b85e0b08d3?q=80&w=1600&auto=format&fit=crop") center/cover no-repeat}
.promo .wrap{position:relative;z-index:2;display:flex;flex-direction:column;align-items:center;text-align:center;gap:24px;padding:var(--sec-pad) 40px}
.promo h2{font-size:clamp(22px,3vw,32px);text-transform:uppercase;letter-spacing:-.01em;line-height:1.15;margin:0;max-width:22ch;color:var(--ink)}

/* ===== SWIPER (dependency-free: scroll-snap + arrow buttons) ===== */
.swiper{position:relative}
/* No scroll-snap and no scroll-behavior:smooth — both fight the JS tween below
   (snap re-snaps every animation frame, which reads as a jump, not a slide).
   Position is driven entirely by JS, so arrows glide and drag tracks the pointer. */
.sw-track{display:flex;gap:var(--sw-gap,1px);overflow-x:auto;-ms-overflow-style:none;scrollbar-width:none;cursor:grab;touch-action:pan-x}
.sw-track::-webkit-scrollbar{display:none}
.sw-track.dragging{cursor:grabbing;user-select:none}
.sw-track.dragging a,.sw-track.dragging img{pointer-events:none}
.sw-track>*{flex:0 0 calc((100% - (var(--sw-gap,1px) * (var(--per,4) - 1))) / var(--per,4))}
.sw-btn{position:absolute;top:50%;transform:translateY(-50%);width:44px;height:44px;border:1px solid var(--hairline);background:var(--canvas);color:var(--ink);display:grid;place-items:center;cursor:pointer;z-index:4;transition:background .2s,border-color .2s,color .2s,opacity .2s}
.sw-btn:hover{background:var(--ink);border-color:var(--ink);color:#fff}
.sw-btn[disabled]{opacity:.25;pointer-events:none}
.sw-prev{left:-22px}
.sw-next{right:-22px}
@media (max-width:1540px){.sw-prev{left:6px}.sw-next{right:6px}}

/* ===== TESTIMONIALS ===== */
.tmonials{background:var(--surface-soft);border-top:1px solid var(--hairline)}
/* left-aligned like every other section heading — .sec-head already does that */
.tmonials .sw-track{--per:3;--sw-gap:20px}
/* Monochrome + M red only — no navy. Flat and 0-radius like the rest of the
   system; the quote is the hero, everything else recedes. */
.tcard{position:relative;overflow:hidden;background:var(--ink);margin:0;
  padding:38px 34px 32px;display:flex;flex-direction:column;gap:22px}
.tcard::before{content:"";position:absolute;left:0;right:0;top:0;height:3px;background:var(--m-red)}
/* oversized ghost quote mark — the editorial device that carries the card */
.tcard::after{content:"\201D";position:absolute;right:18px;bottom:-42px;
  font-family:var(--f-display);font-size:170px;font-weight:700;line-height:1;
  color:#fff;opacity:.055;pointer-events:none}
.tstars{letter-spacing:5px;font-size:14px;color:var(--m-red);line-height:1}
.tcard blockquote{position:relative;z-index:1;margin:0;font-size:18px;font-weight:300;
  line-height:1.6;color:#f2f2f0;letter-spacing:-.005em}
.tcard figcaption{position:relative;z-index:1;margin-top:auto;padding-top:24px;
  border-top:1px solid #2b2b2b;display:flex;align-items:center;gap:14px}
/* flat white disc + initials: crisp at small size, and honest — we have no
   customer photos and will not invent faces for people */
.tavatar{width:46px;height:46px;border-radius:50%;flex:0 0 auto;display:grid;place-items:center;
  background:#fff;color:#0a0a0a;font-size:15px;font-weight:700;letter-spacing:.04em}
.tmeta{display:flex;flex-direction:column;gap:5px;min-width:0}
.tmeta b{font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#fff;line-height:1}
.tmeta span{font-size:14px;color:#8a8a8a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:300}
@media (max-width:1024px){.tmonials .sw-track{--per:2}}
@media (max-width:700px){.tmonials .sw-track{--per:1}}


/* ===== NEWSLETTER — dark band ===== */
.news{background:#000;color:var(--body);position:relative;overflow:hidden;border-top:1px solid var(--hairline);border-bottom:1px solid var(--hairline)}
.news .wrap{position:relative;z-index:2;display:grid;grid-template-columns:1fr 420px;gap:56px;align-items:center;padding:var(--sec-pad) 40px}
.news h2{color:#fff;font-size:clamp(22px,2.6vw,30px);text-transform:uppercase;letter-spacing:-.01em;line-height:1.02;margin:16px 0 14px}
.news p{color:var(--body);font-size:16px;font-weight:300;max-width:46ch;line-height:1.5}
.news form{margin-top:28px;max-width:480px}
.news .row{display:flex;gap:12px}
.news input[type=email]{flex:1;height:52px;border:1px solid var(--hairline);background:var(--surface-card);color:#fff;padding:0 18px;font-family:var(--f);font-size:14px;font-weight:300}
.news input[type=email]::placeholder{color:var(--mute)}
.news input[type=email]:focus{outline:none;border-color:#fff}
.news .btn{height:52px}
.news .chk{display:flex;align-items:center;gap:9px;margin-top:16px;font-size:14px;color:var(--body);cursor:pointer}
.news .chk input{width:16px;height:16px;accent-color:var(--m-red)}
.news .priv{font-size:14px;color:var(--mute);margin-top:14px;display:flex;align-items:center;gap:7px}
.news .art{aspect-ratio:4/3;overflow:hidden;border:1px solid var(--hairline)}
.news .art img{width:100%;height:100%;object-fit:cover}

/* ===== FOOTER (rich, homepage) ===== */
.hp-footer{background:var(--canvas);color:var(--body);border-top:1px solid var(--hairline)}
.hp-footer .ftop{display:grid;grid-template-columns:1.6fr 1fr 1fr 1fr 1fr;gap:40px;padding:64px 0 48px;border-bottom:1px solid var(--hairline)}
.hp-footer .fbrand .flogo{display:flex;align-items:center;gap:12px;margin-bottom:16px}
.hp-footer .fbrand .flogo .m-mark{width:4px;height:26px;background:var(--m-stripe)}
.hp-footer .fbrand .flogo b{font-family:var(--f-display);font-weight:700;font-size:19px;letter-spacing:.02em;text-transform:uppercase;color:#fff}
.hp-footer .fbrand p{font-size:14px;color:var(--body);font-weight:300;max-width:32ch;margin:0 0 18px;line-height:1.6}
.hp-footer .fcontact{display:flex;flex-direction:column;gap:10px;font-size:14px;font-weight:300}
.hp-footer .fcontact .ln{display:flex;align-items:center;gap:10px}.hp-footer .fcontact svg{color:var(--ink);flex:0 0 auto}
.hp-footer .fsocial{display:flex;gap:10px;margin-top:18px}
.hp-footer .fsocial a{width:40px;height:40px;border:1px solid var(--hairline);display:grid;place-items:center;color:var(--body);transition:.2s}
.hp-footer .fsocial a:hover{background:#fff;border-color:#fff;color:#000}
.hp-footer h5{color:#fff;font-size:14px;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:18px;font-weight:700}
.hp-footer .fcol ul{display:flex;flex-direction:column;gap:12px;font-size:14px;list-style:none;margin:0;padding:0}
.hp-footer .fcol a{color:var(--body);font-weight:300;transition:color .2s}
.hp-footer .fcol a:hover{color:#fff}
.hp-footer .fbase{display:flex;align-items:center;gap:20px;flex-wrap:wrap;padding:24px 0;font-size:14px;color:var(--mute)}
.hp-footer .fbase .sp{flex:1}
.hp-footer .fpay{display:flex;gap:8px}
.hp-footer .fpay span{border:1px solid var(--hairline);padding:3px 8px;font-size:14px;font-weight:700;letter-spacing:.06em;font-family:var(--f-mono);color:var(--mute)}

.totop{position:fixed;right:24px;bottom:24px;width:46px;height:46px;background:#fff;color:#000;display:grid;place-items:center;opacity:0;transform:translateY(12px);pointer-events:none;transition:.3s var(--ease);z-index:70;border:0;cursor:pointer}
.totop.show{opacity:1;transform:none;pointer-events:auto}
.totop:hover{background:var(--body-strong)}

/* ===== RESPONSIVE ===== */
@media (max-width:1024px){
  .hero .wrap{grid-template-columns:1fr;gap:36px;padding:88px 40px 92px}
  .searchshell{max-width:534px}
  .news .wrap{grid-template-columns:1fr}.news .art{display:none}
  .catgrid{grid-template-columns:repeat(2,1fr)}
  .ccard.feat,.ccard.wide{grid-column:span 2;grid-row:auto}.ccard.feat .im{aspect-ratio:5/4}
  .hp-footer .ftop{grid-template-columns:1fr 1fr 1fr}.hp-footer .fbrand{grid-column:span 3}
}
@media (max-width:900px){
  .catpanel{display:none}.mcat{display:block}
  .pgrid{grid-template-columns:repeat(2,1fr)}
  .cat-search{width:100%;max-width:540px}
  .trust .wrap{grid-template-columns:1fr 1fr}.trust .b:nth-child(2){border-right:0}.trust .b{border-bottom:1px solid var(--hairline)}
}
@media (max-width:560px){
  .wrap{padding:0 24px}
  .hero .wrap{padding:64px 24px 68px}
  .hero-cta .btn{flex:1}
  .hero-stats>div{padding:2px 20px}
  .grid2{grid-template-columns:1fr}
  .cats .sec-head{flex-direction:column;align-items:flex-start;gap:20px}
  .catgrid{grid-template-columns:1fr}.ccard.feat,.ccard.wide{grid-column:span 1}.ccard.wide{flex-direction:column}.ccard.wide .im{width:auto}
  .trust .wrap{grid-template-columns:1fr}.trust .b{border-right:0}
  .hp-footer .ftop{grid-template-columns:1fr 1fr}.hp-footer .fbrand{grid-column:span 2}
  .news .row{flex-direction:column}.news .btn{width:100%}
  .totop{right:16px;bottom:16px}
}
/* ===== LIGHT-THEME RECONCILIATION (BMW-M light) ===== */
/* hero is the one dark photo band on the light page */
.hero{background:#000}
.hero::before{background:linear-gradient(rgba(0,0,0,.70),rgba(0,0,0,.84)),url("https://images.unsplash.com/photo-1503376780353-7e6692767b70?q=80&w=1920&auto=format&fit=crop") center/cover no-repeat}
.hero-copy h1{color:#fff}
.hero-stats b{color:#fff}
.hero-stats span{color:rgba(255,255,255,.72)}
.hero-stats>div+div{border-left:1px solid rgba(255,255,255,.22)}
.eyebrow.on-navy{color:#e6e6e6}
.hl{color:#fff;box-shadow:inset 0 -.10em 0 var(--m-red);padding-bottom:.02em}
.btn-ghost-light{border-color:rgba(255,255,255,.55);color:#fff}
.btn-ghost-light:hover{background:#fff;border-color:#fff;color:#000}
/* primary CTA sits on the dark hero photo — needs a white fill. Previously it was
   near-black on black, and on hover went transparent + near-black text = invisible. */
.hero .btn-red{background:#fff;border-color:#fff;color:#000}
.hero .btn-red:hover{background:transparent;border-color:#fff;color:#fff}
/* trust strip spans the same width as the find bar above it */
.trust .b{padding:34px 24px;align-items:center}
/* featured category pills */
.fcats{display:flex;gap:10px}
.cpill{height:38px;padding:0 16px;border:1px solid var(--hairline);background:transparent;color:var(--ink);font-family:var(--f);font-size:14px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;cursor:pointer;transition:background .2s,border-color .2s,color .2s}
.cpill:hover{border-color:var(--ink)}
.cpill.on{background:var(--ink);border-color:var(--ink);color:#fff}
.featured .pgrid{transition:opacity .2s}
/* category pills slide at their natural width */
.pillswiper{margin:0 0 26px}
.pillswiper .sw-track{background:none;border:0;padding:1px}
.pillswiper .sw-track>*{flex:0 0 auto}
/* popular-vehicle chips slide at their natural width */
.popular .swiper{flex:1;min-width:0}
.popswiper .sw-track{background:none;border:0;gap:10px;padding:1px}
.popswiper .sw-track>*{flex:0 0 auto}
/* pill + chip rows lay the arrows out as real columns rather than floating them
   over the track, so no pill ever slides underneath an arrow */
.pillswiper,.popswiper{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:10px}
.pillswiper .sw-btn,.popswiper .sw-btn{position:static;transform:none;width:34px;height:34px}
/* popular-vehicle chips: solid black */
.chipv{background:var(--ink);border-color:var(--ink);color:#fff}
.chipv svg{color:rgba(255,255,255,.6)}
.chipv:hover,.chipv.on{background:#000;border-color:#000;color:#fff}
.chipv:hover svg,.chipv.on svg{color:#fff}
/* crumbs and the action buttons must share one centre line — wrapping put them
   on separate flex lines, which is why the crumbs rode ~8px high */
.breadcrumb{display:grid;grid-template-columns:1fr auto;align-items:center;gap:16px}
.breadcrumb .sp{display:none}
.breadcrumb .crumbs{min-width:0;margin-top:auto;margin-bottom:auto;align-items:center}
.trust .b:first-child{padding-left:0}
.trust .b:last-child{padding-right:0}
.tab.on::after{background:var(--ink)}
.opt:hover{background:var(--surface-soft)}
.opt.on{background:var(--surface-soft)}
.mstep .mh .num{background:var(--surface-elevated)}
.mstep.done .mh .num{background:var(--ink);color:#fff}
.chipv.on{border-color:var(--ink);background:var(--surface-elevated);color:var(--ink)}
.chipv.on svg{color:var(--ink)}
.totop{background:var(--ink);color:#fff}
.news{background:var(--surface-soft);border-top:1px solid var(--hairline);border-bottom:1px solid var(--hairline)}
.news h2{color:var(--ink)}
.news input[type=email]{background:var(--canvas);border-color:var(--hairline);color:var(--ink)}
.news input[type=email]:focus{border-color:var(--ink)}
/* footer stays a dark band closing the light page */
.hp-footer{background:var(--header-bg);color:var(--header-body);border-top:1px solid var(--header-line)}
.hp-footer .ftop{border-bottom:1px solid var(--header-line)}
.hp-footer .fbrand .flogo{display:inline-block;margin-bottom:18px}
.hp-footer .fbrand .flogo .brand-logo{height:64px}
.hp-footer .fbrand p,.hp-footer .fcontact,.hp-footer .fcol a{color:var(--header-body)}
.hp-footer .fcontact svg{color:#fff}
.hp-footer h5{color:#fff}
.hp-footer .fcol a:hover{color:#fff}
.hp-footer .fsocial a{border-color:var(--header-line);color:var(--header-body)}
.hp-footer .fsocial a:hover{background:#fff;border-color:#fff;color:#000}
.hp-footer .fbase{color:var(--mute)}
.hp-footer .fpay{display:flex;align-items:center;gap:14px}
.hp-footer .fpay img{height:20px;width:auto;opacity:.7;transition:opacity .2s}
.hp-footer .fpay img:hover{opacity:1}
/* This block re-declares .btn after app.css loads, so the coloured button
   variants must be repeated here or the transparent base .btn wins on tie. */
.btn-accent{background:var(--m-red);border-color:var(--m-red);color:#fff}
.btn-accent:hover{background:#f5402f;border-color:#f5402f;color:#fff}
.btn-wa{background:#128c47;border-color:#128c47;color:#fff}
.btn-wa:hover{background:#0e7439;border-color:#0e7439;color:#fff}
</style>
<noscript><style>.reveal{opacity:1!important;transform:none!important}</style></noscript>
</head>
<body>

<?php include __DIR__ . '/_header.php'; ?>

<!-- ===== HERO ===== -->
<section class="hero">
  <div class="wrap">
    <div class="hero-copy reveal">
      <span class="eyebrow on-navy">Verified fitment on every part</span>
      <h1>The right parts for your <span class="hl">exact machine</span></h1>
      <p>Every part matched to your exact year, make, model, and engine by verified fitment data. Shipped nationwide.</p>
      <div class="hero-cta">
        <a class="btn btn-red" href="<?= e($base) ?>/products">Browse the catalog <span class="ar"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span></a>
        <?php $wa = whatsapp_link('Hi, I need help finding a part.'); ?>
        <a class="btn btn-ghost-light" href="<?= $wa !== '' ? e($wa) : '#faq' ?>"<?= $wa !== '' ? ' target="_blank" rel="noopener"' : '' ?>>Talk to a specialist</a>
      </div>
      <div class="hero-stats">
        <div><b class="count" data-target="<?= (int) ($stats['parts'] ?? 0) ?>">0</b><span>Parts</span></div>
        <div><b class="count" data-target="<?= (int) ($stats['fitments'] ?? 0) ?>">0</b><span>Fitments</span></div>
        <div><b class="count" data-target="<?= (int) ($stats['vehicles'] ?? 0) ?>">0</b><span>Vehicles</span></div>
      </div>
    </div>
  </div>
</section>

<!-- ===== FIND YOUR PARTS — bar straddling the hero and the page ===== -->
<section class="findbar-wrap">
  <div class="wrap">
    <div class="findbar reveal">
      <div class="fb-top">
        <span class="fb-mark" aria-hidden="true"><svg width="26" height="18" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 16"><path d="M2 12h20M4 12l1.5-6h13L20 12M7 6l1-2.5h8L17 6"/><circle cx="7" cy="13" r="1.3"/><circle cx="17" cy="13" r="1.3"/></svg></span>
        <span class="fb-lead"><span class="fb-count">Over 25,000 vehicles covered</span><b>Find Your Parts</b></span>
        <div class="tabs" role="tablist">
          <button class="tab on" id="tab-veh" role="tab" aria-selected="true" data-tab="veh">By Vehicle</button>
          <button class="tab" id="tab-vin" role="tab" aria-selected="false" data-tab="vin">By VIN</button>
        </div>
      </div>

      <div class="fb-body" id="panel-veh" role="tabpanel" aria-labelledby="tab-veh">
        <div class="fb-row">
          <select class="control" id="v-make" aria-label="Make"><option value="">Select Make</option></select>
          <select class="control" id="v-model" aria-label="Model" disabled><option value="">Select Model</option></select>
          <select class="control" id="v-year" aria-label="Year" disabled><option value="">Select Year</option></select>
          <select class="control" id="v-engine" aria-label="Engine" disabled><option value="">Select Engine</option></select>
          <button class="btn btn-red" id="findParts">Find Parts <span class="ar"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span></button>
        </div>
        <div class="msg" id="veh-msg" role="alert"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v5m0 3h.01"/></svg><span>Pick a make, model, year and engine to continue.</span></div>
        <?php if (!empty($recent['slug'])): ?>
        <div class="recent"><span class="ic"><svg width="18" height="12" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 16"><path d="M2 12h20M4 12l1.5-6h13L20 12M7 6l1-2.5h8L17 6"/><circle cx="7" cy="13" r="1.2"/><circle cx="17" cy="13" r="1.2"/></svg></span><span>Recently viewed: <b><?= e($recent['label'] ?? $recent['slug']) ?></b></span><a class="go" href="<?= e($base) ?>/products?vehicle=<?= rawurlencode($recent['slug']) ?>">Resume</a></div>
        <?php endif; ?>
      </div>

      <div class="fb-body" id="panel-vin" role="tabpanel" aria-labelledby="tab-vin" hidden>
        <div class="fb-row fb-row-vin">
          <input class="control mono" id="vin-input" maxlength="17" placeholder="Enter your 17-character VIN" aria-label="VIN" style="text-transform:uppercase;letter-spacing:.04em">
          <button class="btn btn-red" id="searchVin">Search VIN <span class="ar"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span></button>
        </div>
        <div class="msg" id="vin-msg" role="alert"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v5m0 3h.01"/></svg><span>A VIN must be exactly 17 characters.</span></div>
        <div class="msg" id="vin-ok"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path d="m5 13 4 4 10-11"/></svg><span>Vehicle identified — loading matching parts…</span></div>
        <div class="vinnote"><svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4m0-4h.01"/></svg><span>Your VIN is a 17-character code found on the driver-side dashboard, inside the door jamb, or on your registration.</span></div>
      </div>
    </div>
  </div>
</section>

<!-- ===== TRUST STRIP ===== -->
<section class="trust"><div class="wrap">
  <div class="b reveal"><span class="ic"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M12 3l7 3v5c0 5-3 8-7 10-4-2-7-5-7-10V6z"/><path d="m9 12 2 2 4-4"/></svg></span><div><b>Guaranteed Fitment</b><span>Matched to your exact vehicle</span></div></div>
  <div class="b reveal" style="transition-delay:.05s"><span class="ic"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="12" cy="9" r="5.4"/><path d="m8.4 13.4-1.2 7.1 4.8-2.6 4.8 2.6-1.2-7.1"/></svg></span><div><b>Trusted Brands</b><span>OEM &amp; premium aftermarket</span></div></div>
  <div class="b reveal" style="transition-delay:.1s"><span class="ic"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></span><div><b>Secure Ordering</b><span>Encrypted checkout, trusted pay</span></div></div>
  <div class="b reveal" style="transition-delay:.15s"><span class="ic"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3.5-7.1M21 4v5h-5"/></svg></span><div><b>Fast Support</b><span>Real parts specialists on call</span></div></div>
</div></section>

<!-- ===== CATALOG ===== -->
<section class="catalog sec" id="catalog"><div class="wrap">
  <div class="sec-head reveal">
    <h2>Browse Parts by Vehicle</h2>
    <p>Select your vehicle step by step to view parts engineered for the correct fit.</p>
  </div>

  <div class="catpanel reveal">
    <div class="breadcrumb">
      <div class="crumbs" id="crumbs">
        <span class="step" data-c="make"><span class="ph">Make</span></span><span class="sep">›</span>
        <span class="step" data-c="model"><span class="ph">Model</span></span><span class="sep">›</span>
        <span class="step" data-c="year"><span class="ph">Year</span></span><span class="sep">›</span>
        <span class="step" data-c="engine"><span class="ph">Engine</span></span><span class="sep">›</span>
        <span class="step" data-c="cat"><span class="ph">Category</span></span><span class="sep">›</span>
        <span class="step" data-c="sub"><span class="ph">Subcategory</span></span>
      </div>
      <span class="sp"></span>
      <div class="act">
        <button class="txtbtn" id="clearSel">Clear Selection</button>
        <button class="txtbtn">Save Vehicle</button>
        <button class="btn btn-red" id="viewMatch">View Matching Parts <span class="ar"><svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span></button>
      </div>
    </div>
    <div class="cols" id="cols">
      <div class="col" data-col="make"><div class="ch">Make <span class="n">—</span></div><div class="filt"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4-4"/></svg><input placeholder="Filter" aria-label="Filter makes"></div><div class="list"></div></div>
      <div class="col" data-col="model"><div class="ch">Model <span class="n">—</span></div><div class="filt"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4-4"/></svg><input placeholder="Filter" aria-label="Filter models"></div><div class="list"></div></div>
      <div class="col" data-col="year"><div class="ch">Year <span class="n">—</span></div><div class="filt"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4-4"/></svg><input placeholder="Filter" aria-label="Filter years"></div><div class="list"></div></div>
      <div class="col" data-col="engine"><div class="ch">Engine / Trim <span class="n">—</span></div><div class="filt"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4-4"/></svg><input placeholder="Filter" aria-label="Filter engines"></div><div class="list">
        <div class="opt" data-v="3.5L V6">3.5L V6 <span class="cv"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg></span></div>
        <div class="opt" data-v="2.0L L4 Turbo">2.0L L4 Turbo <span class="cv"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg></span></div>
      </div></div>
      <div class="col" data-col="cat"><div class="ch">Part Category <span class="n">—</span></div><div class="filt"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4-4"/></svg><input placeholder="Filter" aria-label="Filter categories"></div><div class="list"></div></div>
      <div class="col" data-col="sub"><div class="ch">Part Subcategory <span class="n">—</span></div><div class="filt"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4-4"/></svg><input placeholder="Filter" aria-label="Filter subcategories"></div><div class="list"></div></div>
    </div>
    <div class="popular">
      <span class="lbl">Popular Vehicles</span>
      <div class="swiper popswiper">
        <button class="sw-btn sw-prev" type="button" aria-label="Previous vehicles"><svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></svg></button>
        <div class="sw-track">
          <?php foreach (($extras['popular'] ?? []) as $pv): ?>
            <button class="chipv" data-make="<?= e($pv['make']) ?>" data-model="<?= e($pv['model']) ?>" data-year="<?= (int) $pv['year'] ?>"><svg width="15" height="10" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 16"><path d="M2 12h20M4 12l1.5-6h13L20 12"/></svg><?= e($pv['label']) ?></button>
          <?php endforeach; ?>
        </div>
        <button class="sw-btn sw-next" type="button" aria-label="Next vehicles"><svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg></button>
      </div>
    </div>
  </div>

  <div class="mcat" id="mcat">
    <div class="mstep open" data-step="make"><button class="mh"><span class="num">1</span>Select Make<span class="val"></span><span class="cv"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg></span></button><div class="mb"><div class="opt" data-v="Toyota">Toyota</div><div class="opt" data-v="Honda">Honda</div><div class="opt" data-v="Acura">Acura</div><div class="opt" data-v="Ford">Ford</div></div></div>
    <div class="mstep" data-step="model" aria-disabled="true"><button class="mh"><span class="num">2</span>Select Model<span class="val"></span><span class="cv"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg></span></button><div class="mb"><div class="opt" data-v="MDX">MDX</div><div class="opt" data-v="TLX">TLX</div><div class="opt" data-v="RDX">RDX</div></div></div>
    <div class="mstep" data-step="year" aria-disabled="true"><button class="mh"><span class="num">3</span>Select Year<span class="val"></span><span class="cv"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg></span></button><div class="mb"><div class="opt" data-v="2024">2024</div><div class="opt" data-v="2023">2023</div><div class="opt" data-v="2022">2022</div><div class="opt" data-v="2021">2021</div><div class="opt" data-v="2020">2020</div></div></div>
    <div class="mstep" data-step="engine" aria-disabled="true"><button class="mh"><span class="num">4</span>Select Engine<span class="val"></span><span class="cv"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg></span></button><div class="mb"><div class="opt" data-v="3.5L V6">3.5L V6</div><div class="opt" data-v="2.0L L4 Turbo">2.0L L4 Turbo</div></div></div>
    <div class="mstep" data-step="cat" aria-disabled="true"><button class="mh"><span class="num">5</span>Select Category<span class="val"></span><span class="cv"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg></span></button><div class="mb"><div class="opt" data-v="Brakes">Brakes</div><div class="opt" data-v="Engine">Engine</div><div class="opt" data-v="Suspension">Suspension</div><div class="opt" data-v="Filters">Filters</div></div></div>
    <button class="btn btn-red btn-block" style="margin-top:8px">View Matching Parts</button>
  </div>
</div></section>

<!-- ===== FEATURED PARTS (daily rotation, seeded by date) ===== -->
<?php if (!empty($featured)): ?>
<section class="featured sec" id="featured"><div class="wrap">
  <div class="sec-head reveal">
    <h2>Featured Parts</h2>
    <p>A fresh selection from the catalog, rotated every day.</p>
  </div>
  <?php $fcats = $extras['categories'] ?? []; ?>
  <?php if ($fcats): ?>
    <div class="swiper pillswiper reveal">
      <button class="sw-btn sw-prev" type="button" aria-label="Previous categories"><svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></svg></button>
      <div class="fcats sw-track">
        <button class="cpill on" data-cat="">All</button>
        <?php foreach ($fcats as $fc): ?>
          <button class="cpill" data-cat="<?= e($fc['slug']) ?>"><?= e($fc['name']) ?></button>
        <?php endforeach; ?>
      </div>
      <button class="sw-btn sw-next" type="button" aria-label="Next categories"><svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg></button>
    </div>
  <?php endif; ?>
  <?php /* no .reveal here — the pill swap drives this element's opacity inline,
           and a reveal transition on the same property fights it */ ?>
  <div class="pgrid" id="featuredTrack">
    <?php foreach ($featured as $f): ?>
      <a class="pcard" href="<?= e($base) ?>/part/<?= e($f['slug']) ?>">
        <span class="pcard-im">
          <img src="<?= e(img_url($f['primary_image_path'])) ?>" alt="<?= e($f['name']) ?>" loading="lazy" onerror="this.style.opacity=0">
        </span>
        <span class="pcard-bd">
          <span class="pcard-title"><?= e($f['name']) ?></span>
          <span class="pcard-price"><?= price_tag($f['price'], (int) ($f['category_id'] ?? 0)) ?></span>
        </span>
      </a>
    <?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>

<!-- ===== PROMO BAND ===== -->
<section class="promo">
  <div class="wrap">
    <h2>All kinds of parts that you need, right here</h2>
    <a class="btn btn-red" href="<?= e($base) ?>/products">Shop Now <span class="ar"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span></a>
  </div>
</section>

<!-- ===== TESTIMONIALS =====
     PLACEHOLDER COPY — these are not real customers. Replace with genuine,
     attributable reviews before this goes live; publishing invented reviews as
     real is deceptive and, in most markets, unlawful. -->
<section class="tmonials sec" id="reviews">
  <div class="wrap">
    <div class="sec-head reveal">
      <h2>What Our Customers Say</h2>
      <p>Real fitment, real installs — straight from the workshop.</p>
    </div>
    <?php
    /* SAMPLE CONTENT — these are not real customers. Swap for genuine, attributable
       reviews before launch; publishing invented reviews as real is deceptive. */
    $reviews = [
      ['n' => 'Marcus T.', 'w' => '2 days ago',  'p' => 'Brembo Brake Kit',      'q' => 'Ordered by VIN and the brake kit was an exact match — no guesswork, no returns. Fitted it the same weekend.'],
      ['n' => 'Sarah K.',  'w' => '1 week ago',  'p' => 'Denso Alternator',      'q' => 'The drill-down got me to the right part in under a minute. Shipping was quick and the packaging was solid.'],
      ['n' => 'James R.',  'w' => '2 weeks ago', 'p' => 'Moog Control Arm',      'q' => 'Hard-to-find part for an older model, listed against the exact engine option. Exactly what turned up.'],
      ['n' => 'Priya S.',  'w' => '3 weeks ago', 'p' => 'Bosch Oxygen Sensor',   'q' => 'Fitment data was spot on for my trim. I have been burned by guesswork before — not here.'],
      ['n' => 'Danny O.',  'w' => '1 month ago', 'p' => 'Gates Timing Belt Kit', 'q' => 'Full kit arrived complete, nothing missing. The spec table matched the part exactly.'],
      ['n' => 'Elena V.',  'w' => '1 month ago', 'p' => 'ACDelco Wheel Bearing', 'q' => 'Ran the VIN, picked the part, done. Second order this month and both were right first time.'],
      ['n' => 'Tom H.',    'w' => '2 months ago','p' => 'K&N Air Filter',        'q' => 'Prices are fair and the listings actually tell you what fits. Easy to recommend to the shop.'],
      ['n' => 'Aisha M.',  'w' => '2 months ago','p' => 'Monroe Shock Absorber', 'q' => 'Ordered a pair for a 12-year-old SUV and they fitted without a fight. Great turnaround.'],
    ];
    $initials = function (string $name): string {
        $out = '';
        foreach (preg_split('/\s+/', trim($name)) as $w) {
            if ($w !== '') { $out .= strtoupper($w[0]); }
        }
        return substr($out, 0, 2);
    };
    ?>
    <div class="swiper reveal">
      <button class="sw-btn sw-prev" type="button" aria-label="Previous reviews"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></svg></button>
      <div class="sw-track">
        <?php foreach ($reviews as $r): ?>
          <figure class="tcard">
            <span class="tstars" aria-label="5 out of 5">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
            <blockquote>&ldquo;<?= e($r['q']) ?>&rdquo;</blockquote>
            <figcaption>
              <span class="tavatar" aria-hidden="true"><?= e($initials($r['n'])) ?></span>
              <span class="tmeta"><b><?= e($r['n']) ?></b><span><?= e($r['w']) ?> &middot; <?= e($r['p']) ?></span></span>
            </figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
      <button class="sw-btn sw-next" type="button" aria-label="Next reviews"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg></button>
    </div>
  </div>
</section>

<?php include __DIR__ . '/_faq.php'; ?>



<!-- ===== FOOTER (homepage, rich) ===== -->
<?php include __DIR__ . '/_footer.php'; ?>

<button class="totop" id="totop" aria-label="Back to top"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 19V5M6 11l6-6 6 6"/></svg></button>

<script>window.SP={base:<?= json_encode($base, JSON_UNESCAPED_SLASHES) ?>};</script>
<script>
(function(){
  var rm=matchMedia('(prefers-reduced-motion: reduce)').matches;
  var SP_BASE=(window.SP&&window.SP.base)||'';
  function api(p){return fetch(SP_BASE+'/api'+p,{headers:{Accept:'application/json'}}).then(function(r){return r.ok?r.json():[]}).catch(function(){return[]})}
  function esc(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML}
  // back-to-top (sticky header + drawer are handled by _header.php)
  var totop=document.getElementById('totop');
  addEventListener('scroll',function(){totop.classList.toggle('show',scrollY>600)},{passive:true});
  totop.addEventListener('click',function(){scrollTo({top:0,behavior:rm?'auto':'smooth'})});
  // reveal
  var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target)}})},{threshold:0,rootMargin:'0px 0px -8% 0px'});
  document.querySelectorAll('.reveal').forEach(function(el){io.observe(el)});
  requestAnimationFrame(function(){document.querySelectorAll('.reveal').forEach(function(el){if(el.getBoundingClientRect().top<innerHeight)el.classList.add('in')})});
  // hero counters — ease to the real catalog numbers over 3s, once in view
  (function(){
    var els=document.querySelectorAll('.hero-stats .count');
    if(!els.length)return;
    // floor, not round: "25K+" must mean *at least* 25K (25,555 -> 25K+, not 26K+)
    function fmt(n){
      if(n>=1e6)return Math.floor(n/1e6)+'M+';
      if(n>=1e3)return Math.floor(n/1e3)+'K+';
      return String(Math.floor(n));
    }
    function run(el){
      var target=parseInt(el.getAttribute('data-target'),10)||0;
      if(rm||!target){el.textContent=fmt(target);return}
      // Timer, not requestAnimationFrame: rAF is suspended whenever the page
      // isn't compositing (background tab / headless), which would freeze the
      // counters at 0. Progress is time-based, so it stays smooth either way.
      var t0=Date.now(),dur=3000;
      var id=setInterval(function(){
        var p=Math.min(1,(Date.now()-t0)/dur);
        el.textContent=fmt(target*(1-Math.pow(1-p,3)));   // ease-out cubic
        if(p>=1){clearInterval(id);el.textContent=fmt(target)}
      },16);
    }
    // Run straight away — the hero is always at the top of the page, and gating
    // this on IntersectionObserver silently no-ops wherever the page isn't
    // actively compositing (headless/background tabs), leaving the counters at 0.
    [].forEach.call(els,function(el){run(el)});
  })();
  // newsletter signup -> stored so admin can see subscribers
  var nf=document.getElementById('newsForm');
  if(nf)nf.addEventListener('submit',function(ev){
    ev.preventDefault();
    var em=document.getElementById('newsEmail'),msg=document.getElementById('news-msg');
    var b=nf.querySelector('button[type=submit]'),old=b.innerHTML;
    b.innerHTML='Subscribing…';
    var fd=new FormData(); fd.append('email',(em.value||'').trim());
    fetch(SP_BASE+'/newsletter',{method:'POST',body:fd})
      .then(function(r){return r.json()})
      .then(function(d){
        msg.querySelector('span').textContent=d.ok?'Thanks — you are subscribed.':(d.error||'Could not subscribe.');
        msg.className='msg '+(d.ok?'ok':'err');
        if(d.ok)em.value='';
      })
      .catch(function(){msg.querySelector('span').textContent='Could not subscribe right now.';msg.className='msg err'})
      .then(function(){b.innerHTML=old});
  });
  // recently-viewed strip is now server-rendered from the 7-day sp_last_vehicle
  // cookie (set on every drill in CatalogController::products) — no JS needed.
  // hero tabs
  document.querySelectorAll('.tab').forEach(function(t){t.addEventListener('click',function(){
    document.querySelectorAll('.tab').forEach(function(x){x.classList.remove('on');x.setAttribute('aria-selected','false')});
    t.classList.add('on');t.setAttribute('aria-selected','true');
    var which=t.dataset.tab;
    document.getElementById('panel-veh').hidden=which!=='veh';
    document.getElementById('panel-vin').hidden=which!=='vin';
  })});
  // cascading vehicle selects -> real API (year -> make -> model -> engine/vehicle)
  var vy=document.getElementById('v-year'),vm=document.getElementById('v-make'),
      vmo=document.getElementById('v-model'),ve=document.getElementById('v-engine');
  function optFill(sel,items,ph,valfn,txtfn){
    if(!sel)return; sel.innerHTML='<option value="">'+ph+'</option>';
    items.forEach(function(it){var o=document.createElement('option');o.value=valfn(it);o.textContent=txtfn(it);sel.appendChild(o)});
  }
  if(vm){
    // Make -> Model -> Year -> Engine (same order as the catalog browser)
    optFill(vmo,[],'Select Model'); optFill(vy,[],'Select Year'); optFill(ve,[],'Select Engine');
    vmo.disabled=vy.disabled=ve.disabled=true;
    api('/tree/makes').then(function(ms){
      optFill(vm,ms,'Select Make',function(m){return m.slug},function(m){return m.name})});
    vm.addEventListener('change',function(){
      vy.disabled=ve.disabled=true; optFill(vy,[],'Select Year'); optFill(ve,[],'Select Engine');
      document.getElementById('veh-msg').classList.remove('err');
      if(!vm.value){vmo.disabled=true;optFill(vmo,[],'Select Model');return}
      vmo.disabled=false;
      api('/models?make='+encodeURIComponent(vm.value)).then(function(ms){
        optFill(vmo,ms,'Select Model',function(m){return m.slug},function(m){return m.name})});
    });
    vmo.addEventListener('change',function(){
      ve.disabled=true; optFill(ve,[],'Select Engine');
      if(!vmo.value){vy.disabled=true;optFill(vy,[],'Select Year');return}
      vy.disabled=false;
      api('/tree/years?make='+encodeURIComponent(vm.value)+'&model='+encodeURIComponent(vmo.value)).then(function(ys){
        optFill(vy,ys,'Select Year',function(y){return y},function(y){return y})});
    });
    vy.addEventListener('change',function(){
      if(!vy.value){ve.disabled=true;optFill(ve,[],'Select Engine');return}
      ve.disabled=false;
      api('/vehicles?year='+encodeURIComponent(vy.value)+'&make='+encodeURIComponent(vm.value)+'&model='+encodeURIComponent(vmo.value)).then(function(vs){
        optFill(ve,vs,'Select Engine',function(v){return v.slug},function(v){return v.label})});
    });
  }
  var findBtn=document.getElementById('findParts');
  findBtn&&findBtn.addEventListener('click',function(){
    var m=document.getElementById('veh-msg');
    if(!ve||!ve.value){m.classList.add('err');return}
    m.classList.remove('err'); this.innerHTML='Loading matching parts…';
    location.href=SP_BASE+'/products?vehicle='+encodeURIComponent(ve.value);
  });
  // VIN -> decode (NHTSA vPIC) -> match our catalog -> open that exact vehicle
  var vin=document.getElementById('vin-input');
  var vinBtn=document.getElementById('searchVin');
  function nz(s){return String(s==null?'':s).toUpperCase().replace(/[^A-Z0-9]/g,'')}
  function vinFail(msg){
    var err=document.getElementById('vin-msg'),ok=document.getElementById('vin-ok');
    ok.classList.remove('ok');
    err.querySelector('span').textContent=msg;
    err.classList.add('err');
    if(vinBtn)vinBtn.innerHTML='Search VIN';
  }
  // Exact match first. Prefix matching is deliberately guarded: a bare "F1" must NOT
  // swallow a decoded "F-150" (that shipped 2013 VINs to a 1948 truck).
  function pickModel(mods,name){
    mods=mods||[]; var c=nz(name); if(!c)return null;
    var hit=mods.filter(function(x){return nz(x.name)===c})[0];
    if(hit)return hit;
    // catalog name extends the decoded one: "F150" -> "F150 HERITAGE"
    if(c.length>=3){
      hit=mods.filter(function(x){return nz(x.name).indexOf(c)===0})[0];
      if(hit)return hit;
    }
    // decoded extends the catalog one: "SILVERADO1500" -> "SILVERADO".
    // Needs a substantial stem, else short names match far too much.
    return mods.filter(function(x){var a=nz(x.name);return a.length>=5&&c.indexOf(a)===0})[0] || null;
  }
  // No exact-year vehicle: select the make (and the model if we carry it) in the
  // catalog so the user picks a year themselves. We never auto-open a different
  // year — wrong-year parts are a fitment hazard, not a helpful guess.
  function selectInCatalog(slug,name,yr,mo){
    vinFail('We do not list a ' + yr + ' ' + name + ' ' + (mo||'') + ' — selected ' + name + ' below; choose a model and year.');
    var o=[].filter.call(document.querySelectorAll('.cols .col[data-col="make"] .list .opt'),
                         function(x){return x.dataset.v===slug})[0];
    if(!o)return null;
    o.click(); o.scrollIntoView({block:'nearest'});
    var cp=document.querySelector('.catpanel');
    if(cp)cp.scrollIntoView({behavior:rm?'auto':'smooth',block:'center'});
    setTimeout(function(){                     // models load async; select ours if present
      var c=nz(mo);
      var m=[].filter.call(document.querySelectorAll('.cols .col[data-col="model"] .list .opt'),
                           function(x){return nz(x.dataset.t)===c})[0];
      if(m){m.click(); m.scrollIntoView({block:'nearest'});}
    },1500);
    return null;
  }
  vinBtn&&vinBtn.addEventListener('click',function(){
    var err=document.getElementById('vin-msg'),ok=document.getElementById('vin-ok'),b=this;
    err.classList.remove('err');ok.classList.remove('ok');
    var v=(vin.value||'').trim();
    if(v.length!==17){vinFail('A VIN must be exactly 17 characters.');vin.focus();return}
    b.innerHTML='Decoding VIN…';
    var yr,mkName,moName,mkSlug;
    fetch('https://vpic.nhtsa.dot.gov/api/vehicles/DecodeVinValues/'+encodeURIComponent(v)+'?format=json')
      .then(function(r){return r.json()})
      .then(function(d){
        var r=(d.Results&&d.Results[0])||{};
        yr=parseInt(r.ModelYear,10); mkName=r.Make; moName=r.Model;
        if(!yr||!mkName)throw new Error('Could not decode that VIN.');
        ok.querySelector('span').textContent='Identified '+yr+' '+mkName+' '+(moName||'')+' — finding parts…';
        ok.classList.add('ok');
        return api('/tree/makes');
      })
      .then(function(ms){
        var hit=(ms||[]).filter(function(x){return nz(x.name)===nz(mkName)})[0];
        if(!hit)throw new Error(mkName+' is not in our catalog yet.');
        mkSlug=hit.slug;
        // Only an exact year+model is a safe fitment match — never substitute another year.
        return api('/models?make='+encodeURIComponent(mkSlug)+'&year='+yr);
      })
      .then(function(mods){
        var mh=pickModel(mods,moName);
        if(!mh)return selectInCatalog(mkSlug,mkName,yr,moName);
        return api('/vehicles?year='+yr+'&make='+encodeURIComponent(mkSlug)+'&model='+encodeURIComponent(mh.slug))
          .then(function(vs){
            if(!vs||!vs.length)return selectInCatalog(mkSlug,mkName,yr,moName);
            location.href=SP_BASE+'/products?vehicle='+encodeURIComponent(vs[0].slug);
          });
      })
      .catch(function(e){vinFail(e&&e.message?e.message:'VIN lookup failed.')});
  });
  vin&&vin.addEventListener('input',function(){this.value=this.value.toUpperCase().replace(/[^A-Z0-9]/g,'');document.getElementById('vin-msg').classList.remove('err')});
  // catalog: desktop 5-column browser -> real API drill-down
  var C_sel={year:{v:'',t:''},make:{v:'',t:''},model:{v:'',t:''},engine:{v:'',t:''},cat:{v:'',t:''},sub:{v:'',t:''}};
  var C_order=['make','model','year','engine','cat','sub'];
  var C_label={year:'Year',make:'Make',model:'Model',engine:'Engine',cat:'Category',sub:'Subcategory'};
  function C_list(n){return document.querySelector('.cols .col[data-col="'+n+'"] .list')}
  function C_count(n,c){var el=document.querySelector('.cols .col[data-col="'+n+'"] .ch .n');if(el)el.textContent=(c==null?'—':c)}
  function C_crumb(){C_order.forEach(function(c){
    var s=document.querySelector('.crumbs .step[data-c="'+c+'"]');if(!s)return;
    s.innerHTML=C_sel[c].v?'<b style="color:var(--ink)">'+esc(C_sel[c].t)+'</b>':'<span class="ph">'+C_label[c]+'</span>';
  })}
  var C_chev='<span class="cv"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg></span>';
  function C_flags(codes){
    if(!codes||!codes.length)return '';
    return '<span class="flags">'+codes.map(function(c){c=String(c).toLowerCase();
      return '<img class="flag" src="https://flagcdn.com/16x12/'+c+'.png" alt="'+esc(c.toUpperCase())+'" title="'+esc(c.toUpperCase())+'" loading="lazy">';}).join('')+'</span>';
  }
  function C_render(name,items,valfn,txtfn,countfn,flagsfn){
    var list=C_list(name);if(!list)return;
    if(!items.length){list.innerHTML='<div class="opt" style="color:var(--mute);cursor:default">None found</div>';C_count(name,0);return}
    C_count(name,items.length);list.innerHTML='';
    items.forEach(function(it){var v=valfn(it),t=txtfn(it),n=countfn?countfn(it):null,fl=flagsfn?flagsfn(it):null;
      var o=document.createElement('div');o.className='opt';o.dataset.v=v;o.dataset.t=t;
      o.innerHTML='<span class="opt-l">'+esc(t)+C_flags(fl)+'</span>'+(n!=null?'<span class="n">'+esc(n)+'</span>':C_chev);list.appendChild(o)});
  }
  function C_clearFrom(i){for(var j=i;j<C_order.length;j++){C_sel[C_order[j]]={v:'',t:''};var l=C_list(C_order[j]);if(l){l.innerHTML='';C_count(C_order[j],null)}}}
  // drill order: Make -> Model -> Year -> Engine -> Category
  function C_next(name){var mk=C_sel.make.v,y=C_sel.year.v,mo=C_sel.model.v,en=C_sel.engine.v;
    if(name==='make')api('/models?make='+encodeURIComponent(mk)).then(function(d){C_render('model',d,function(x){return x.slug},function(x){return x.name})});
    else if(name==='model')api('/tree/years?make='+encodeURIComponent(mk)+'&model='+encodeURIComponent(mo)).then(function(d){C_render('year',d,function(x){return x},function(x){return x})});
    else if(name==='year')api('/vehicles?year='+encodeURIComponent(y)+'&make='+encodeURIComponent(mk)+'&model='+encodeURIComponent(mo)).then(function(d){C_render('engine',d,function(x){return x.slug},function(x){return x.label})});
    else if(name==='engine')api('/tree/groups?vehicle='+encodeURIComponent(en)).then(function(d){C_render('cat',d,function(x){return x.slug},function(x){return x.name},function(x){return x.n})});
    else if(name==='cat')api('/tree/categories?vehicle='+encodeURIComponent(en)+'&group='+encodeURIComponent(C_sel.cat.v)).then(function(d){C_render('sub',d,function(x){return x.slug},function(x){return x.name},function(x){return x.n})});
  }
  document.querySelectorAll('.cols .col').forEach(function(col){
    var cname=col.dataset.col,list=col.querySelector('.list');
    if(list)list.addEventListener('click',function(ev){var opt=ev.target.closest('.opt');if(!opt||!opt.dataset.v)return;
      col.querySelectorAll('.opt').forEach(function(o){o.classList.remove('on')});opt.classList.add('on');
      C_sel[cname]={v:opt.dataset.v,t:opt.dataset.t};C_clearFrom(C_order.indexOf(cname)+1);C_crumb();
      if(cname!=='sub')C_next(cname);
    });
    var f=col.querySelector('.filt input');if(f)f.addEventListener('input',function(){var q=this.value.toLowerCase();col.querySelectorAll('.opt').forEach(function(o){o.style.display=o.textContent.toLowerCase().indexOf(q)>-1?'':'none'})});
  });
  if(document.querySelector('.cols')){
    ['year','model','engine','cat','sub'].forEach(function(n){var l=C_list(n);if(l)l.innerHTML='';C_count(n,null)});
    var ml=C_list('make');if(ml)ml.innerHTML='';C_count('make',null);
    api('/tree/makes').then(function(ms){C_render('make',ms,function(x){return x.slug},function(x){return x.name},function(x){return x.n},function(x){return x.markets})});
  }
  var clearSel=document.getElementById('clearSel');
  clearSel&&clearSel.addEventListener('click',function(){C_clearFrom(0);C_crumb()});
  var vmatch=document.getElementById('viewMatch');
  if(vmatch)vmatch.addEventListener('click',function(){
    if(!C_sel.engine.v)return;
    var q='vehicle='+encodeURIComponent(C_sel.engine.v);
    var cat=C_sel.sub.v||C_sel.cat.v;                 // deepest category chosen
    if(cat)q+='&category='+encodeURIComponent(cat);
    location.href=SP_BASE+'/products?'+q;
  });
  // mobile catalog accordion (progressive)
  var mOrder=['make','model','year','engine','cat'];
  document.querySelectorAll('.mstep').forEach(function(st){
    st.querySelector('.mh').addEventListener('click',function(){
      if(st.getAttribute('aria-disabled')==='true')return;
      var wasOpen=st.classList.contains('open');
      document.querySelectorAll('.mstep').forEach(function(s){s.classList.remove('open')});
      if(!wasOpen)st.classList.add('open');
    });
    st.querySelectorAll('.opt').forEach(function(opt){opt.addEventListener('click',function(){
      st.querySelectorAll('.opt').forEach(function(o){o.classList.remove('on')});opt.classList.add('on');
      st.classList.add('done');st.querySelector('.val').textContent=opt.dataset.v;
      var i=mOrder.indexOf(st.dataset.step),nxt=document.querySelector('.mstep[data-step="'+mOrder[i+1]+'"]');
      st.classList.remove('open');
      if(nxt){nxt.setAttribute('aria-disabled','false');nxt.classList.add('open')}
    })});
  });
  // popular-vehicle chips -> drill the catalog through Make > Model > Year.
  // Each level loads async, so poll for the option to appear before clicking it.
  document.querySelectorAll('.chipv').forEach(function(chip){
    chip.addEventListener('click',function(){
      document.querySelectorAll('.chipv').forEach(function(c){c.classList.remove('on')});
      chip.classList.add('on');
      var cp=document.querySelector('.catpanel');
      if(cp)cp.scrollIntoView({behavior:rm?'auto':'smooth',block:'center'});
      function pick(col,val,then){
        if(!val){return}
        var tries=0,iv=setInterval(function(){
          var o=[].filter.call(document.querySelectorAll('.cols .col[data-col="'+col+'"] .list .opt'),
                               function(x){return String(x.dataset.v)===String(val)})[0];
          if(o){clearInterval(iv);o.click();o.scrollIntoView({block:'nearest'});if(then)setTimeout(then,200)}
          else if(++tries>40){clearInterval(iv)}    // give up after ~6s
        },150);
      }
      pick('make',chip.dataset.make,function(){
        pick('model',chip.dataset.model,function(){
          pick('year',chip.dataset.year);
        });
      });
    });
  });
  // sliders — no library: a scroll-snap track paged by the arrow buttons
  function initSwipers(){
    [].forEach.call(document.querySelectorAll('.swiper'),function(sw){
      var track=sw.querySelector('.sw-track');
      if(!track||sw.dataset.swInit)return;
      sw.dataset.swInit='1';
      var prev=sw.querySelector('.sw-prev'),next=sw.querySelector('.sw-next');
      function step(){return Math.max(140,Math.round(track.clientWidth*0.9))}
      function sync(){
        var max=track.scrollWidth-track.clientWidth-2,
            fits=track.scrollWidth<=track.clientWidth+2;
        [prev,next].forEach(function(b){if(b)b.style.display=fits?'none':''});
        if(prev)prev.disabled=track.scrollLeft<=2;
        if(next)next.disabled=track.scrollLeft>=max;
      }
      function glide(delta){ glideTo(track.scrollLeft+delta) }
      /* Land on a slide boundary. Measured per child rather than assuming a fixed
         slide width, because the pill and chip tracks hold variable-width items. */
      function snapNearest(){
        var kids=track.children,tl=track.getBoundingClientRect().left,
            cur=track.scrollLeft,best=null,bd=Infinity;
        for(var i=0;i<kids.length;i++){
          var to=cur+(kids[i].getBoundingClientRect().left-tl),d=Math.abs(to-cur);
          if(d<bd){bd=d;best=to}
        }
        if(best!==null)glideTo(best);
      }
      function glideTo(target){
        var max=Math.max(0,track.scrollWidth-track.clientWidth),
            from=track.scrollLeft,
            to=Math.max(0,Math.min(max,target));
        if(Math.abs(to-from)<1){sync();return}
        if(rm){track.scrollLeft=to;sync();return}
        var dur=450,t0=null;
        cancelAnimationFrame(track._raf); clearTimeout(track._land);
        // rAF, not setInterval: vsync-aligned frames are what make this read as a
        // slide rather than a jump.
        function frame(ts){
          if(t0===null)t0=ts;
          var p=Math.min(1,(ts-t0)/dur);
          track.scrollLeft=from+(to-from)*(1-Math.pow(1-p,3));
          if(p<1){track._raf=requestAnimationFrame(frame)}
          else{track.scrollLeft=to;sync()}
        }
        track._raf=requestAnimationFrame(frame);
        // if rAF never ticks (tab hidden / compositing suspended), still land on target
        track._land=setTimeout(function(){
          if(Math.abs(track.scrollLeft-to)>1){
            cancelAnimationFrame(track._raf);track.scrollLeft=to;sync();
          }
        },dur+150);
      }
      prev&&prev.addEventListener('click',function(){glide(-step())});
      next&&next.addEventListener('click',function(){glide(step())});
      track.addEventListener('scroll',sync,{passive:true});
      addEventListener('resize',sync);

      // drag to slide. Touch is left to the browser — native momentum scrolling
      // beats anything reimplemented here — so this covers mouse and pen only.
      var down=false,dragged=0,startX=0,startLeft=0,pid=null;
      track.addEventListener('pointerdown',function(e){
        if(e.pointerType==='touch'||e.button!==0)return;
        down=true;dragged=0;startX=e.clientX;startLeft=track.scrollLeft;pid=e.pointerId;
        cancelAnimationFrame(track._raf);clearTimeout(track._land);
      });
      track.addEventListener('pointermove',function(e){
        if(!down||e.pointerId!==pid)return;
        var dx=e.clientX-startX;
        if(dragged||Math.abs(dx)>3){
          dragged=Math.max(dragged,Math.abs(dx));
          track.classList.add('dragging');
          try{if(!track.hasPointerCapture(pid))track.setPointerCapture(pid)}catch(_){}
          track.scrollLeft=startLeft-dx;
          e.preventDefault();
        }
      });
      function endDrag(){
        if(!down)return;
        var wasDrag=dragged>5;
        down=false;track.classList.remove('dragging');
        try{if(pid!==null&&track.hasPointerCapture(pid))track.releasePointerCapture(pid)}catch(_){}
        pid=null;
        if(wasDrag)snapNearest(); else sync();
      }
      track.addEventListener('pointerup',endDrag);
      track.addEventListener('pointercancel',endDrag);
      // a drag that ends on a card must not also open that card
      track.addEventListener('click',function(e){
        if(dragged>5){e.preventDefault();e.stopPropagation();dragged=0}
      },true);

      sw._sync=sync; sync();
    });
  }
  initSwipers();
  // featured category pills -> swap the 8 cards for that category
  (function(){
    var pills=document.querySelectorAll('.cpill'),grid=document.getElementById('featuredTrack');
    if(!pills.length||!grid)return;
    function card(p){
      return '<a class="pcard" href="'+SP_BASE+'/part/'+encodeURIComponent(p.slug)+'">'+
        '<span class="pcard-im"><img src="'+esc(p.img)+'" alt="'+esc(p.name)+'" loading="lazy" onerror="this.style.opacity=0"></span>'+
        '<span class="pcard-bd">'+
        '<span class="pcard-title">'+esc(p.name)+'</span>'+
        '<span class="pcard-price">'+esc(p.price)+'</span></span></a>';
    }
    [].forEach.call(pills,function(b){b.addEventListener('click',function(){
      [].forEach.call(pills,function(x){x.classList.remove('on')});
      b.classList.add('on');
      grid.style.opacity=.35;
      api('/featured'+(b.dataset.cat?'?category='+encodeURIComponent(b.dataset.cat):''))
        .then(function(rows){
          grid.innerHTML=(rows&&rows.length)?rows.map(card).join('')
            :'<p class="empty" style="grid-column:1/-1">No parts in this category yet.</p>';
          grid.style.opacity=1;
        });
    })});
  })();
})();
</script>
</body>
</html>
