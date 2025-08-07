<?php
/**
 * Public-facing functionality
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic_Public {
    
    private $plugin_name;
    private $version;
    private $assets_enqueued = false;
    
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_shortcode('master_critic_list', [$this, 'render_master_list_shortcode']);
        add_action('wp_footer', [$this, 'maybe_enqueue_assets']);
    }
    
    /**
     * Render master list shortcode
     */
    public function render_master_list_shortcode($atts) {
        $atts = shortcode_atts([
            'id' => 0,
            'show_scores' => true,
            'show_summaries' => true,
            'show_price_tiers' => true,
            'show_neighborhoods' => false
        ], $atts);
        
        $set_id = intval($atts['id']);
        if ($set_id <= 0) {
            return '<div class="zpmc-error">Error: Master set ID is required</div>';
        }
        
        // Get set data
        $set = $this->get_master_set($set_id);
        if (!$set) {
            return '<div class="zpmc-error">Error: Master set not found or not published</div>';
        }
        
        // Get items grouped by tier
        $items_by_tier = $this->get_items_by_tier($set_id);
        if (empty($items_by_tier)) {
            return '<div class="zpmc-error">No restaurants found in this master set</div>';
        }
        
        // Mark that assets should be enqueued
        $this->assets_enqueued = true;
        
        // Generate output
        ob_start();
        $this->render_master_list($set, $items_by_tier, $atts);
        return ob_get_clean();
    }
    
    /**
     * Get master set by ID
     */
    private function get_master_set($set_id) {
        global $wpdb;
        
        $sets_table = ZipPicks_Master_Critic_Database::get_sets_table();
        $set = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$sets_table} WHERE id = %d AND status = 'published'",
            $set_id
        ));
        
        return $set;
    }
    
    /**
     * Get items grouped by tier
     */
    private function get_items_by_tier($set_id) {
        global $wpdb;
        
        $items_table = ZipPicks_Master_Critic_Database::get_items_table();
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$items_table} 
             WHERE set_id = %d AND status = 'active' 
             ORDER BY 
                CASE tier 
                    WHEN 'Essential' THEN 1 
                    WHEN 'Notable' THEN 2 
                    WHEN 'Worthy' THEN 3 
                    ELSE 4 
                END, 
                score DESC, 
                position ASC",
            $set_id
        ));
        
        if (empty($items)) {
            return [];
        }
        
        // Group by tier
        $grouped = [];
        foreach ($items as $item) {
            $tier = !empty($item->tier) ? $item->tier : 'Other';
            if (!isset($grouped[$tier])) {
                $grouped[$tier] = [];
            }
            $grouped[$tier][] = $item;
        }
        
        return $grouped;
    }
    
    /**
     * Render master list HTML
     */
    private function render_master_list($set, $items_by_tier, $atts) {
        $show_scores = filter_var($atts['show_scores'], FILTER_VALIDATE_BOOLEAN);
        $show_summaries = filter_var($atts['show_summaries'], FILTER_VALIDATE_BOOLEAN);
        $show_price_tiers = filter_var($atts['show_price_tiers'], FILTER_VALIDATE_BOOLEAN);
        $show_neighborhoods = filter_var($atts['show_neighborhoods'], FILTER_VALIDATE_BOOLEAN);
        
        ?>
        <div class="zpmc-master-list" data-set-id="<?php echo esc_attr($set->id); ?>">
            
            <!-- List Header -->
            <div class="zpmc-header">
                <h2 class="zpmc-title"><?php echo esc_html($set->set_name); ?></h2>
                <?php if (!empty($set->zip_code)): ?>
                    <p class="zpmc-location">
                        <?php echo esc_html($set->category); ?> in <?php echo esc_html($this->get_location_name($set->zip_code)); ?>
                    </p>
                <?php endif; ?>
                <p class="zpmc-count"><?php echo count($items_by_tier, COUNT_RECURSIVE) - count($items_by_tier); ?> restaurants</p>
            </div>
            
            <!-- Tier Groups -->
            <?php 
            $tier_order = ['Essential', 'Notable', 'Worthy'];
            foreach ($tier_order as $tier_name):
                if (!isset($items_by_tier[$tier_name])) continue;
                $items = $items_by_tier[$tier_name];
            ?>
                <div class="zpmc-tier-group zpmc-tier-<?php echo strtolower($tier_name); ?>">
                    <h3 class="zpmc-tier-title"><?php echo esc_html($tier_name); ?> <span class="zpmc-tier-count">(<?php echo count($items); ?>)</span></h3>
                    
                    <div class="zpmc-restaurants">
                        <?php foreach ($items as $item): ?>
                            <div class="zpmc-restaurant" data-business-id="<?php echo esc_attr($item->business_id ?? ''); ?>">
                                
                                <!-- Restaurant Header -->
                                <div class="zpmc-restaurant-header">
                                    <h4 class="zpmc-restaurant-name"><?php echo esc_html($item->business_name); ?></h4>
                                    
                                    <div class="zpmc-restaurant-meta">
                                        <?php if ($show_scores && !empty($item->score)): ?>
                                            <span class="zpmc-score" title="ZipPicks Score"><?php echo esc_html($item->score); ?></span>
                                        <?php endif; ?>
                                        
                                        <?php if ($show_price_tiers && !empty($item->price_tier)): ?>
                                            <span class="zpmc-price-tier"><?php echo esc_html($item->price_tier); ?></span>
                                        <?php endif; ?>
                                        
                                        <?php if ($show_neighborhoods && !empty($item->neighborhood)): ?>
                                            <span class="zpmc-neighborhood"><?php echo esc_html($item->neighborhood); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Restaurant Details -->
                                <?php if ($show_summaries && !empty($item->summary)): ?>
                                    <div class="zpmc-restaurant-summary">
                                        <p><?php echo wp_kses_post($item->summary); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Top Dishes -->
                                <?php if (!empty($item->top_dishes)): ?>
                                    <div class="zpmc-top-dishes">
                                        <strong>Must Try:</strong> <?php echo esc_html($item->top_dishes); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Contact Info -->
                                <?php if (!empty($item->address) || !empty($item->phone)): ?>
                                    <div class="zpmc-contact-info">
                                        <?php if (!empty($item->address)): ?>
                                            <span class="zpmc-address"><?php echo esc_html($item->address); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($item->phone)): ?>
                                            <span class="zpmc-phone"><?php echo esc_html($item->phone); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Vibes -->
                                <?php if (!empty($item->vibes)): ?>
                                    <div class="zpmc-vibes">
                                        <strong>Vibes:</strong> <?php echo esc_html($item->vibes); ?>
                                    </div>
                                <?php endif; ?>
                                
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Handle any "Other" tier items -->
            <?php if (isset($items_by_tier['Other'])): ?>
                <div class="zpmc-tier-group zpmc-tier-other">
                    <h3 class="zpmc-tier-title">Other Recommendations <span class="zpmc-tier-count">(<?php echo count($items_by_tier['Other']); ?>)</span></h3>
                    
                    <div class="zpmc-restaurants">
                        <?php foreach ($items_by_tier['Other'] as $item): ?>
                            <!-- Same structure as above for consistency -->
                            <div class="zpmc-restaurant" data-business-id="<?php echo esc_attr($item->business_id ?? ''); ?>">
                                <div class="zpmc-restaurant-header">
                                    <h4 class="zpmc-restaurant-name"><?php echo esc_html($item->business_name); ?></h4>
                                    <div class="zpmc-restaurant-meta">
                                        <?php if ($show_scores && !empty($item->score)): ?>
                                            <span class="zpmc-score"><?php echo esc_html($item->score); ?></span>
                                        <?php endif; ?>
                                        <?php if ($show_price_tiers && !empty($item->price_tier)): ?>
                                            <span class="zpmc-price-tier"><?php echo esc_html($item->price_tier); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($show_summaries && !empty($item->summary)): ?>
                                    <div class="zpmc-restaurant-summary">
                                        <p><?php echo wp_kses_post($item->summary); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Attribution -->
            <div class="zpmc-attribution">
                <p><small>Curated by ZipPicks Master Critic</small></p>
            </div>
            
        </div>
        <?php
    }
    
    /**
     * Get location name from ZIP code
     */
    private function get_location_name($zip_code) {
        $zip_map = [
            '94566' => 'Pleasanton, CA',
            '94588' => 'Pleasanton, CA', 
            '94568' => 'Dublin, CA',
            '94550' => 'Livermore, CA',
            '90210' => 'Beverly Hills, CA',
            '94301' => 'Palo Alto, CA',
            '94107' => 'San Francisco, CA',
            '94102' => 'San Francisco, CA'
        ];
        
        return $zip_map[$zip_code] ?? $zip_code;
    }
    
    /**
     * Conditionally enqueue assets
     */
    public function maybe_enqueue_assets() {
        if (!$this->assets_enqueued) {
            return;
        }
        
        $this->enqueue_styles();
        $this->enqueue_scripts();
    }
    
    /**
     * Enqueue CSS
     */
    private function enqueue_styles() {
        // Inline CSS for simplicity and performance
        $css = '
        .zpmc-master-list {
            max-width: 800px;
            margin: 2em 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        }
        
        .zpmc-header {
            margin-bottom: 2em;
            text-align: center;
            border-bottom: 2px solid #e1e5e9;
            padding-bottom: 1em;
        }
        
        .zpmc-title {
            font-size: 2em;
            color: #1a1a1a;
            margin-bottom: 0.5em;
        }
        
        .zpmc-location {
            color: #666;
            font-size: 1.1em;
            margin-bottom: 0.5em;
        }
        
        .zpmc-count {
            color: #999;
            font-weight: 500;
        }
        
        .zpmc-tier-group {
            margin-bottom: 3em;
        }
        
        .zpmc-tier-title {
            font-size: 1.5em;
            color: #1a1a1a;
            margin-bottom: 1em;
            border-left: 4px solid #0073aa;
            padding-left: 1em;
        }
        
        .zpmc-tier-essential .zpmc-tier-title {
            border-color: #d63638;
        }
        
        .zpmc-tier-notable .zpmc-tier-title {
            border-color: #f56e28;
        }
        
        .zpmc-tier-worthy .zpmc-tier-title {
            border-color: #007cba;
        }
        
        .zpmc-tier-count {
            color: #666;
            font-weight: normal;
        }
        
        .zpmc-restaurants {
            display: grid;
            gap: 1.5em;
        }
        
        .zpmc-restaurant {
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 1.5em;
            background: #fff;
            transition: box-shadow 0.2s ease;
        }
        
        .zpmc-restaurant:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .zpmc-restaurant-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1em;
        }
        
        .zpmc-restaurant-name {
            font-size: 1.3em;
            color: #1a1a1a;
            margin: 0;
            flex: 1;
            margin-right: 1em;
        }
        
        .zpmc-restaurant-meta {
            display: flex;
            gap: 0.5em;
            align-items: center;
            flex-shrink: 0;
        }
        
        .zpmc-score {
            background: #0073aa;
            color: white;
            padding: 0.25em 0.75em;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9em;
        }
        
        .zpmc-price-tier {
            background: #f0f0f1;
            color: #3c434a;
            padding: 0.25em 0.75em;
            border-radius: 4px;
            font-weight: 500;
            font-size: 0.9em;
        }
        
        .zpmc-neighborhood {
            color: #666;
            font-style: italic;
        }
        
        .zpmc-restaurant-summary {
            margin-bottom: 1em;
            line-height: 1.6;
            color: #3c434a;
        }
        
        .zpmc-top-dishes,
        .zpmc-vibes {
            margin-bottom: 0.75em;
            font-size: 0.95em;
            color: #3c434a;
        }
        
        .zpmc-contact-info {
            margin-bottom: 0.75em;
            font-size: 0.9em;
            color: #666;
        }
        
        .zpmc-contact-info span {
            margin-right: 1em;
        }
        
        .zpmc-attribution {
            text-align: center;
            margin-top: 3em;
            padding-top: 2em;
            border-top: 1px solid #e1e5e9;
        }
        
        .zpmc-error {
            background: #fcf2f2;
            border: 1px solid #cc1818;
            color: #cc1818;
            padding: 1em;
            border-radius: 4px;
            text-align: center;
        }
        
        @media (max-width: 600px) {
            .zpmc-restaurant-header {
                flex-direction: column;
                gap: 0.5em;
            }
            
            .zpmc-restaurant-name {
                margin-right: 0;
            }
            
            .zpmc-restaurant-meta {
                align-self: flex-start;
            }
        }
        ';
        
        wp_add_inline_style('wp-block-library', $css);
    }
    
    /**
     * Enqueue JavaScript (minimal)
     */
    private function enqueue_scripts() {
        $js = '
        document.addEventListener("DOMContentLoaded", function() {
            // Simple enhancement for restaurant cards
            const restaurants = document.querySelectorAll(".zpmc-restaurant");
            
            restaurants.forEach(function(restaurant) {
                restaurant.addEventListener("click", function() {
                    const businessId = restaurant.dataset.businessId;
                    if (businessId) {
                        // Optional: Track clicks for analytics
                        if (typeof gtag !== "undefined") {
                            gtag("event", "click", {
                                event_category: "Master Critic",
                                event_label: businessId
                            });
                        }
                    }
                });
            });
        });
        ';
        
        wp_add_inline_script('wp-polyfill', $js);
    }
}