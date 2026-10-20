<?php
function config(string $key) { return $GLOBALS['config'][$key] ?? null; }
function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function url(string $route = '', array $params = []): string { return rtrim(config('base_url'), '/').'/index.php?'.http_build_query(array_merge(['r'=>$route], $params)); }
function asset(string $path): string { return rtrim(config('base_url'), '/').'/public/'.ltrim($path, '/'); }
function money($paise): string { return '₹'.number_format(((int)$paise)/100, 2); }
function csrf(): string { return '<input type="hidden" name="_token" value="'.e($_SESSION['csrf']).'">'; }
function redirect(string $route, array $params=[]): void { header('Location: '.url($route,$params)); exit; }
function flash(string $text, string $type='success'): void { $_SESSION['flash'] = [$text,$type]; }
function setting(string $key, string $default=''): string { return App\Models\Setting::get($key,$default); }
function user() { return App\Core\Auth::user(); }
function render(string $view, array $data=[], string $layout='store'): void { $data=App\Core\ViewData::compose($view,$layout,$data);extract($data);ob_start(); require ROOT.'/app/Views/'.$view.'.php'; $content=ob_get_clean(); require ROOT.'/app/Views/layouts/'.$layout.'.php'; }
function post(string $key, string $default=''): string { return trim((string)($_POST[$key] ?? $default)); }
function require_fields(array $fields): void { foreach ($fields as $field) if (post($field)==='') throw new RuntimeException(ucfirst(str_replace('_',' ',$field)).' is required.'); }
