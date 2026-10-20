<?php
// Keep this check before controllers: the visitor page needs no database.
if (is_file(ROOT.'/storage/maintenance.flag')) {
    header('Cache-Control: no-store, max-age=0');
    $allowed = $route === 'admin' || str_starts_with($route, 'admin/')
        || ($route === 'payment/webhook' && $method === 'POST');
    if (!$allowed) {
        // Only a fully authenticated administrator may preview the store.
        try {
            $allowed = App\Core\Auth::admin() !== null;
        } catch (Throwable $e) {
            $allowed = false;
        }
    }
    if (!$allowed) {
        http_response_code(503);
        header('Retry-After: 3600');
        header('X-Robots-Tag: noindex, nofollow');
        if (str_starts_with($route, 'payment/') || $route === 'cart/add'
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Our store is temporarily under maintenance. Please try again later.']);
        } else {
            header('Content-Type: text/html; charset=utf-8');
            require ROOT.'/app/Views/store/maintenance.php';
        }
        exit;
    }
}
