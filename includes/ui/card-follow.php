<?php
/**
 * Follow Button UI Component
 *
 * @package ZipPicks\Core
 * @since 1.0.0
 * 
 * Usage: 
 * echo zippicks_render_follow_button([
 *     'user_id' => 123,
 *     'type' => 'user',
 *     'followed' => false,
 *     'count' => 42,
 *     'show_count' => true,
 *     'size' => 'medium',
 *     'class' => 'custom-class'
 * ]);
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Extract args with defaults
$defaults = [
    'user_id' => 0,
    'type' => 'user', // user, business, critic
    'followed' => false,
    'count' => 0,
    'show_count' => true,
    'size' => 'medium', // small, medium, large
    'class' => '',
    'label_follow' => __('Follow', 'zippicks-core'),
    'label_following' => __('Following', 'zippicks-core'),
    'label_unfollow' => __('Unfollow', 'zippicks-core'),
    'disabled' => false,
    'icon' => true
];

$args = wp_parse_args($args ?? [], $defaults);

// Build button classes
$button_classes = [
    'zip-follow-button',
    'zip-follow-button--' . esc_attr($args['size']),
    'zip-follow-button--' . esc_attr($args['type']),
];

if ($args['followed']) {
    $button_classes[] = 'zip-follow-button--following';
}

if ($args['disabled']) {
    $button_classes[] = 'zip-follow-button--disabled';
}

if (!empty($args['class'])) {
    $button_classes[] = esc_attr($args['class']);
}

// Determine button text
$button_text = $args['followed'] ? $args['label_following'] : $args['label_follow'];
$hover_text = $args['followed'] ? $args['label_unfollow'] : $args['label_follow'];

// Build data attributes
$data_attrs = [
    'data-user-id' => esc_attr($args['user_id']),
    'data-type' => esc_attr($args['type']),
    'data-followed' => $args['followed'] ? 'true' : 'false',
    'data-label-follow' => esc_attr($args['label_follow']),
    'data-label-following' => esc_attr($args['label_following']),
    'data-label-unfollow' => esc_attr($args['label_unfollow']),
];

?>

<button 
    type="button"
    class="<?php echo esc_attr(implode(' ', $button_classes)); ?>"
    <?php echo implode(' ', array_map(function($key, $value) {
        return $key . '="' . $value . '"';
    }, array_keys($data_attrs), $data_attrs)); ?>
    <?php echo $args['disabled'] ? 'disabled' : ''; ?>
>
    <span class="zip-follow-button__inner">
        <?php if ($args['icon']): ?>
            <span class="zip-follow-button__icon">
                <?php if ($args['followed']): ?>
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                        <path d="M8 3a5 5 0 1 0 0 10A5 5 0 0 0 8 3zm0 8a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/>
                        <path d="M11.5 5.5L7 10l-2.5-2.5L3 9l4 4 6-6z"/>
                    </svg>
                <?php else: ?>
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                        <path d="M8 4a4 4 0 1 0 0 8 4 4 0 0 0 0-8zM2 8a6 6 0 1 1 12 0A6 6 0 0 1 2 8z"/>
                        <path d="M8 7V5h1v2h2v1H9v2H8V8H6V7h2z"/>
                    </svg>
                <?php endif; ?>
            </span>
        <?php endif; ?>
        
        <span class="zip-follow-button__text">
            <?php echo esc_html($button_text); ?>
        </span>
        
        <span class="zip-follow-button__hover-text">
            <?php echo esc_html($hover_text); ?>
        </span>
    </span>
    
    <?php if ($args['show_count'] && $args['count'] > 0): ?>
        <span class="zip-follow-button__count">
            <?php echo function_exists('zippicks_format_number') 
                ? zippicks_format_number($args['count']) 
                : number_format($args['count']); ?>
        </span>
    <?php endif; ?>
</button>

<style>
/* Follow Button Styles - Inline for component encapsulation */
.zip-follow-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border: 1px solid #ddd;
    background: #fff;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    color: #333;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}

.zip-follow-button:hover {
    background: #f5f5f5;
    border-color: #999;
}

.zip-follow-button--following {
    background: #333;
    color: #fff;
    border-color: #333;
}

.zip-follow-button--following:hover {
    background: #d32f2f;
    border-color: #d32f2f;
}

.zip-follow-button--disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.zip-follow-button--small {
    padding: 4px 12px;
    font-size: 12px;
}

.zip-follow-button--large {
    padding: 12px 24px;
    font-size: 16px;
}

.zip-follow-button__inner {
    display: flex;
    align-items: center;
    gap: 6px;
}

.zip-follow-button__icon {
    display: flex;
    align-items: center;
}

.zip-follow-button__icon svg {
    width: 1em;
    height: 1em;
}

.zip-follow-button__text {
    display: inline-block;
}

.zip-follow-button__hover-text {
    display: none;
}

.zip-follow-button--following:hover .zip-follow-button__text {
    display: none;
}

.zip-follow-button--following:hover .zip-follow-button__hover-text {
    display: inline-block;
}

.zip-follow-button__count {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    background: rgba(0, 0, 0, 0.05);
    border-radius: 12px;
    font-size: 0.85em;
    font-weight: normal;
}

.zip-follow-button--following .zip-follow-button__count {
    background: rgba(255, 255, 255, 0.2);
}

/* Type-specific styles */
.zip-follow-button--business {
    border-radius: 20px;
}

.zip-follow-button--critic {
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: 0.5px;
}

/* Loading state */
.zip-follow-button.is-loading {
    pointer-events: none;
}

.zip-follow-button.is-loading .zip-follow-button__inner {
    opacity: 0.5;
}

.zip-follow-button.is-loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 16px;
    height: 16px;
    margin: -8px 0 0 -8px;
    border: 2px solid #333;
    border-radius: 50%;
    border-top-color: transparent;
    animation: zip-follow-spin 0.8s linear infinite;
}

@keyframes zip-follow-spin {
    to { transform: rotate(360deg); }
}
</style>