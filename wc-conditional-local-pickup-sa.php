<?php
/**
 * Plugin Name: WC Conditional Local Pickup (SA)
 * Plugin URI: https://github.com/SaintHossam/
 * Description: إظهار "الاستلام من المتجر" فقط إذا كانت مدينة الوجهة جدة/ينبع (بكل تهجئاتها). في غير ذلك تُخفى local_pickup فقط وتبقى باقي طرق الشحن.
 *              Show "Local Pickup" only if the destination city is Jeddah/Yanbu (all variations). Otherwise, hide only local_pickup and keep other shipping methods.
 * Author: Saint Hossam
 * Author URI: https://github.com/SaintHossam/
 * Version: 1.1.0
 * Requires at least: 5.0
 * Tested up to: 6.6
 * Requires PHP: 7.4
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-conditional-local-pickup
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * نقطة القوة هنا: الاعتماد على $package['destination'] بدل WC()->customer لضمان دقة المدينة/الدولة.
 * Strength point: Relying on $package['destination'] instead of WC()->customer to ensure accurate city/country data.
 */
add_filter('woocommerce_package_rates', 'wcclpsa_filter_local_pickup_rates', 9999, 2);

function wcclpsa_filter_local_pickup_rates(array $rates, array $package): array
{
    $destination = isset($package['destination']) && is_array($package['destination'])
        ? $package['destination']
        : [];

    $country = strtoupper((string) ($destination['country'] ?? ''));
    $cityRaw = (string) ($destination['city'] ?? '');

    // إذا ليست السعودية، لا تغييرات.
    // If not Saudi Arabia, make no changes.
    if ($country !== 'SA') {
        return $rates;
    }

    $city = wcclpsa_normalize_city($cityRaw);

    // قائمة المدن المسموح بها (مطبّعة مسبقاً لمقارنة سريعة)
    // Allowed cities list (pre-normalized for fast comparison)
    static $ALLOWED = null;
    if ($ALLOWED === null) {
        $allowedCities = [
            // Arabic
            'جدة', 'جده', 'ينبع', 'ينبع البحر',
            // Latin
            'jeddah', 'jiddah', 'jaddah',
            'yanbu', 'yanbu al bahr', 'yanbu al-bahr', 'yanbualbahr',
        ];
        $ALLOWED = array_values(array_unique(array_map('wcclpsa_normalize_city', $allowedCities)));
    }

    $isAllowed = $city !== '' && in_array($city, $ALLOWED, true);

    // إن كانت المدينة مسموحاً بها نترك كل طرق الشحن كما هي.
    // If city is allowed, keep all shipping methods.
    if ($isAllowed) {
        return $rates;
    }

    // إخفاء local_pickup فقط.
    // Hide only local_pickup.
    foreach ($rates as $rateId => $rate) {
        $methodId = is_object($rate) && isset($rate->method_id) ? (string) $rate->method_id : '';
        if ($methodId === 'local_pickup' || strpos($methodId, 'local_pickup') === 0) {
            unset($rates[$rateId]);
        }
    }

    return $rates;
}

/**
 * تطبيع قوي لاسم المدينة العربية/اللاتينية.
 * WHY: لتلافي اختلافات الإدخال (تشكيل/همزات/شرطات/مسافات/حالات).
 * Strong normalization for Arabic/Latin city names.
 * WHY: To avoid input variations (diacritics, hamza forms, dashes, spaces, case differences).
 */
function wcclpsa_normalize_city(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    // إزالة مسافات مكررة
    // Remove duplicate spaces
    $value = preg_replace('/\s+/u', ' ', $value);

    // توحيد بعض الحروف العربية
    // Normalize some Arabic letters
    $map = [
        'أ' => 'ا', 'آ' => 'ا', 'إ' => 'ا',
        'ة' => 'ه',
        'ى' => 'ي',
    ];
    $value = strtr($value, $map);

    // إزالة التشكيل العربي
    // Remove Arabic diacritics
    $value = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $value);

    // خفض الحالة للاتيني
    // Lowercase for Latin
    $value = mb_strtolower($value, 'UTF-8');

    // إزالة علامات ترقيم/شرطات/نقاط لتوحيد
    // Remove punctuation/dashes/dots to unify forms like "yanbu al-bahr", "yanbu al bahr", "yanbualbahr"
    $value = str_replace(['-', '_', '.', ','], ' ', $value);
    $value = preg_replace('/\s+/u', ' ', $value);

    // قص المسافات
    // Trim spaces
    $value = trim($value);

    return $value;
}

/**
 * مُساعد (اختياري): تمكين تتبع للقيمة المطبّعة عند الحاجة.
 * مثال الاستخدام:
 *   do_action('wcclpsa_debug_city', $cityRaw, wcclpsa_normalize_city($cityRaw));
 * Helper (optional): Enable tracking for normalized value when needed.
 * Example usage:
 *   do_action('wcclpsa_debug_city', $cityRaw, wcclpsa_normalize_city($cityRaw));
 */
add_action('wcclpsa_debug_city', function (string $raw, string $normalized) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[WC-CLP] City raw: ' . $raw . ' | normalized: ' . $normalized);
    }
}, 10, 2);


/**
 * اجعل حقل المدينة يُعيد حساب الشحن تلقائياً عند التغيير (Checkout & Cart).
 * WHY: بعض الثيمات تُسقط class التحديث أو لا تُطلق أحداث WooCommerce.
 * Make the city field automatically recalculate shipping when changed (Checkout & Cart).
 * WHY: Some themes drop the update class or don't trigger WooCommerce events.
 */
add_filter('woocommerce_default_address_fields', function(array $fields): array {
    if (isset($fields['city'])) {
        $classes = isset($fields['city']['class']) && is_array($fields['city']['class']) ? $fields['city']['class'] : [];
        if (! in_array('update_totals_on_change', $classes, true)) {
            $classes[] = 'update_totals_on_change';
        }
        $fields['city']['class'] = $classes;
    }
    return $fields;
}, 20);

add_action('wp_enqueue_scripts', function () {
    if (! (function_exists('is_checkout') && (is_checkout() || is_cart()))) {
        return;
    }

    $handle = 'wcclpsa-refresh';
    wp_register_script($handle, '', ['jquery'], '1.0.0', true);
    $js = <<<'JS'
    jQuery(function($){
        var debounce = function(fn, wait){ var t; return function(){ clearTimeout(t); var args = arguments, ctx = this; t = setTimeout(function(){ fn.apply(ctx, args); }, wait); }; };
        var refresh = debounce(function(){
            if ($('form.checkout').length) {
                $(document.body).trigger('update_checkout');
            } else if ($('form.woocommerce-cart-form').length) {
                var $btn = $('button[name="calc_shipping"]');
                if ($btn.length) { $btn.prop('disabled', false).trigger('click'); }
            }
        }, 400);

        $(document).on('input change', 'input[name="shipping_city"], input[name="billing_city"], #calc_shipping_city, select[name="shipping_country"], select[name="billing_country"]', refresh);
    });
    JS;
    wp_add_inline_script($handle, $js);
    wp_enqueue_script($handle);
});

/**
 * Patch: بعض المتاجر في السعودية تستخدم حقل الولاية STATE كقائمة مدن.
 * نزيل الفلتر القديم ونستبدله بإصدار يدعم state code/label.
 * Patch: Some stores in Saudi Arabia use the STATE field as a city list.
 * Remove the old filter and replace with a version supporting state code/label.
 */
add_action('init', function(){
    remove_filter('woocommerce_package_rates', 'wcclpsa_filter_local_pickup_rates', 9999);
    add_filter('woocommerce_package_rates', 'wcclpsa_filter_local_pickup_rates_v2', 9999, 2);
});

function wcclpsa_filter_local_pickup_rates_v2(array $rates, array $package): array
{
    $destination = isset($package['destination']) && is_array($package['destination']) ? $package['destination'] : [];

    $country   = strtoupper((string) ($destination['country'] ?? ''));
    $cityRaw   = (string) ($destination['city'] ?? '');
    $stateCode = (string) ($destination['state'] ?? '');

    // إذا ليست السعودية، لا تغييرات.
    // If not Saudi Arabia, no changes.
    if ($country !== 'SA') {
        return $rates;
    }

    $stateLabel = '';
    if ($stateCode !== '') {
        $states = function_exists('WC') ? (WC()->countries->get_states('SA') ?: []) : [];
        if (isset($states[$stateCode])) {
            $stateLabel = (string) $states[$stateCode];
        }
    }

    $cityNorm  = wcclpsa_normalize_city($cityRaw);
    $stateNorm = wcclpsa_normalize_city($stateLabel);

    // قائمة المدن المسموح بها + أكواد الولاية المسموح بها
    // Allowed cities list + allowed state codes
    static $ALLOWED = null;
    static $ALLOWED_CODES = null;
    if ($ALLOWED === null) {
        $allowedCities = [
            'جدة', 'جده', 'ينبع', 'ينبع البحر',
            'jeddah', 'jiddah', 'jaddah',
            'yanbu', 'yanbu al bahr', 'yanbu al-bahr', 'yanbualbahr',
        ];
        $ALLOWED = array_values(array_unique(array_map('wcclpsa_normalize_city', $allowedCities)));
        $ALLOWED_CODES = ['SAMKJI','SAMDYB'];
    }

    $isAllowed = (
        ($cityNorm !== '' && in_array($cityNorm, $ALLOWED, true)) ||
        ($stateNorm !== '' && in_array($stateNorm, $ALLOWED, true)) ||
        ($stateCode !== '' && in_array(strtoupper($stateCode), $ALLOWED_CODES, true))
    );

    // إذا المدينة أو الولاية مسموح بها، اترك كل طرق الشحن.
    // If city or state is allowed, keep all shipping methods.
    if ($isAllowed) {
        return $rates;
    }

    // إخفاء local_pickup فقط.
    // Hide only local_pickup.
    foreach ($rates as $rateId => $rate) {
        $methodId = is_object($rate) && isset($rate->method_id) ? (string) $rate->method_id : '';
        if ($methodId === 'local_pickup' || strpos($methodId, 'local_pickup') === 0) {
            unset($rates[$rateId]);
        }
    }

    return $rates;
}

// أضف تحديث تلقائي عند تغيير state
// Add auto-refresh when state changes
add_action('wp_enqueue_scripts', function () {
    if (! (function_exists('is_checkout') && (is_checkout() || is_cart()))) {
        return;
    }
    $handle = 'wcclpsa-refresh-state';
    wp_register_script($handle, '', ['jquery'], '1.0.1', true);
    $js = <<<'JS'
    jQuery(function($){
        var refresh = function(){
            if ($('form.checkout').length) {
                $(document.body).trigger('update_checkout');
            } else if ($('form.woocommerce-cart-form').length) {
                var $btn = $('button[name="calc_shipping"]');
                if ($btn.length) { $btn.prop('disabled', false).trigger('click'); }
            }
        };
        $(document).on('change', 'select[name="shipping_state"], select[name="billing_state"]', refresh);
    });
    JS;
    wp_add_inline_script($handle, $js);
    wp_enqueue_script($handle);
});

// أضف كلاس التحديث على حقل state أيضاً
// Add update class to state field as well
add_filter('woocommerce_default_address_fields', function(array $fields): array {
    if (isset($fields['state'])) {
        $classes = isset($fields['state']['class']) && is_array($fields['state']['class']) ? $fields['state']['class'] : [];
        if (! in_array('update_totals_on_change', $classes, true)) {
            $classes[] = 'update_totals_on_change';
        }
        $fields['state']['class'] = $classes;
    }
    return $fields;
}, 21);
