<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Cart;

class CartController extends Controller
{
    public function add(): void
    {
        $cart = new Cart();
        $sku = (string) ($_POST['sku'] ?? '');
        $qty = max(1, (int) ($_POST['qty'] ?? 1));
        $variantId = max(0, (int) ($_POST['variant_id'] ?? 0));   // 0 = default price
        if ($sku !== '') { $cart->addBySku($sku, $qty, $variantId); }

        if ($this->wantsJson()) { $this->json($this->miniData($cart)); return; }

        // Return to where they were, or the cart.
        $back = $_POST['back'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
        if ($back && str_contains($back, ($_SERVER['HTTP_HOST'] ?? ''))) {
            header('Location: ' . $back);
        } else {
            $this->redirect('/cart');
        }
        exit;
    }

    public function view(): void
    {
        $cart = new Cart();
        $this->render('cart', [
            'items'    => $cart->items(),
            'totals'   => $cart->totals(),
            'freeOver' => $this->freeOver(),
        ], 'Cart — Supreme Parts');
    }

    public function update(): void
    {
        $cart = new Cart();
        $partId    = (int) ($_POST['part_id'] ?? 0);
        $variantId = max(0, (int) ($_POST['variant_id'] ?? 0));
        $cart->setQty($partId, (int) ($_POST['qty'] ?? 0), $variantId);
        if ($this->wantsJson()) { $this->cartJson($cart, $partId, $variantId); return; }
        $this->redirect('/cart');
    }

    public function remove(): void
    {
        $cart = new Cart();
        $partId    = (int) ($_POST['part_id'] ?? 0);
        $variantId = max(0, (int) ($_POST['variant_id'] ?? 0));
        $cart->remove($partId, $variantId);
        if ($this->wantsJson()) { $this->cartJson($cart, $partId, $variantId); return; }
        $this->redirect('/cart');
    }

    /** Effective free-shipping threshold: admin setting overrides config. 0 = off. */
    private function freeOver(): float
    {
        $cfg = (require BASE_DIR . '/config/config.php')['shipping'];
        $v = setting('delivery_free_over', null);
        return ($v !== null && $v !== '') ? (float) $v : (float) ($cfg['free_over'] ?? 0);
    }

    /** GET /cart/data — mini-cart JSON for the slide-out drawer. */
    public function data(): void
    {
        $this->json($this->miniData(new Cart()));
    }

    /** Compact cart snapshot for the drawer (items + count + subtotal). */
    private function miniData(Cart $cart): array
    {
        $items = [];
        foreach ($cart->items() as $it) {
            $items[] = [
                'part_id'    => (int) $it['part_id'],
                'variant_id' => (int) $it['variant_id'],
                'name'       => $it['name'],
                'slug'       => $it['sku'],
                'qty'        => (int) $it['quantity'],
                'unit_price' => money($it['unit_price']),
                'line_total' => money($it['line_total']),
                'img'        => img_url($it['primary_image_path'] ?? ''),
            ];
        }
        $t = $cart->totals();
        return [
            'ok'       => true,
            'count'    => $cart->count(),
            'empty'    => $cart->count() === 0,
            'subtotal' => money($t['subtotal']),
            'items'    => $items,
        ];
    }

    private function wantsJson(): bool
    {
        return stripos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false
            || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    /** JSON snapshot after a cart mutation, so the cart page can update in place. */
    private function cartJson(Cart $cart, int $partId, int $variantId): void
    {
        $line = null;
        foreach ($cart->items() as $it) {
            if ((int) $it['part_id'] === $partId && (int) $it['variant_id'] === $variantId) {
                $line = $it; break;
            }
        }
        $t     = $cart->totals();
        $count = $cart->count();
        $free  = $this->freeOver();
        $remaining = ($free > 0 && $t['subtotal'] < $free) ? ($free - $t['subtotal']) : 0.0;
        $this->json([
            'ok'            => true,
            'removed'       => $line === null,
            'quantity'      => $line ? (int) $line['quantity'] : 0,
            'lineTotal'     => $line ? money($line['line_total']) : null,
            'count'         => $count,
            'empty'         => $count === 0,
            'subtotal'      => money($t['subtotal']),
            'shipping'      => $t['shipping'] > 0 ? money($t['shipping']) : 'Free',
            'shippingFree'  => $t['shipping'] <= 0,
            'grand'         => money($t['grand']),
            'freeEligible'  => $free > 0,
            'freeReached'   => $free > 0 && $remaining <= 0,
            'freeRemaining' => $remaining > 0 ? money($remaining) : null,
        ]);
    }
}
