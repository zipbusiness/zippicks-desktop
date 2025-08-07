<?php
/**
 * Schema Types and Constants
 * 
 * Defines all Schema.org types and ZipPicks extensions used throughout the platform
 *
 * @package ZipPicks_Schema
 * @since 1.0.0
 */

class ZipPicks_Schema_Types {
    
    /**
     * Schema.org context
     */
    const CONTEXT = 'https://schema.org';
    
    /**
     * Core Schema.org types
     */
    const TYPE_RESTAURANT = 'Restaurant';
    const TYPE_LOCAL_BUSINESS = 'LocalBusiness';
    const TYPE_ITEM_LIST = 'ItemList';
    const TYPE_LIST_ITEM = 'ListItem';
    const TYPE_REVIEW = 'Review';
    const TYPE_AGGREGATE_RATING = 'AggregateRating';
    const TYPE_RATING = 'Rating';
    const TYPE_ORGANIZATION = 'Organization';
    const TYPE_PERSON = 'Person';
    const TYPE_POSTAL_ADDRESS = 'PostalAddress';
    const TYPE_GEO_COORDINATES = 'GeoCoordinates';
    const TYPE_OPENING_HOURS = 'OpeningHours';
    const TYPE_MENU = 'Menu';
    const TYPE_MENU_ITEM = 'MenuItem';
    const TYPE_OFFER = 'Offer';
    const TYPE_PRICE_SPECIFICATION = 'PriceSpecification';
    
    /**
     * ZipPicks custom extensions (using x-zippicks namespace)
     */
    const ZIPPICKS_MASTER_SCORE = 'x-zippicks:masterScore';
    const ZIPPICKS_CLASSIFICATION = 'x-zippicks:classification';
    const ZIPPICKS_VIBES = 'x-zippicks:vibes';
    const ZIPPICKS_TASTE_DNA = 'x-zippicks:tasteDNA';
    const ZIPPICKS_VERIFIED_DATE = 'x-zippicks:verifiedDate';
    const ZIPPICKS_PILLAR_SCORES = 'x-zippicks:pillarScores';
    const ZIPPICKS_GENERATION_ID = 'x-zippicks:generationId';
    const ZIPPICKS_AI_PROVIDER = 'x-zippicks:aiProvider';
    const ZIPPICKS_ZPID = 'x-zippicks:zpid';
    const ZIPPICKS_LIST_TYPE = 'x-zippicks:listType';
    const ZIPPICKS_CITY_CONTEXT = 'x-zippicks:cityContext';
    
    /**
     * Common schema properties
     */
    const PROP_NAME = 'name';
    const PROP_DESCRIPTION = 'description';
    const PROP_URL = 'url';
    const PROP_IMAGE = 'image';
    const PROP_ADDRESS = 'address';
    const PROP_TELEPHONE = 'telephone';
    const PROP_EMAIL = 'email';
    const PROP_OPENING_HOURS = 'openingHours';
    const PROP_SERVES_CUISINE = 'servesCuisine';
    const PROP_PRICE_RANGE = 'priceRange';
    const PROP_AGGREGATE_RATING = 'aggregateRating';
    const PROP_REVIEW = 'review';
    const PROP_MENU = 'hasMenu';
    const PROP_GEO = 'geo';
    const PROP_LATITUDE = 'latitude';
    const PROP_LONGITUDE = 'longitude';
    const PROP_STREET_ADDRESS = 'streetAddress';
    const PROP_ADDRESS_LOCALITY = 'addressLocality';
    const PROP_ADDRESS_REGION = 'addressRegion';
    const PROP_POSTAL_CODE = 'postalCode';
    const PROP_ADDRESS_COUNTRY = 'addressCountry';
    const PROP_RATING_VALUE = 'ratingValue';
    const PROP_BEST_RATING = 'bestRating';
    const PROP_WORST_RATING = 'worstRating';
    const PROP_RATING_COUNT = 'ratingCount';
    const PROP_REVIEW_COUNT = 'reviewCount';
    const PROP_ITEM_LIST_ELEMENT = 'itemListElement';
    const PROP_ITEM_LIST_ORDER = 'itemListOrder';
    const PROP_POSITION = 'position';
    const PROP_ITEM = 'item';
    const PROP_AUTHOR = 'author';
    const PROP_DATE_PUBLISHED = 'datePublished';
    const PROP_DATE_MODIFIED = 'dateModified';
    const PROP_REVIEW_RATING = 'reviewRating';
    const PROP_REVIEW_BODY = 'reviewBody';
    
    /**
     * List ordering types
     */
    const ORDER_UNORDERED = 'ItemListOrderType.ItemListUnordered';
    const ORDER_ASCENDING = 'ItemListOrderType.ItemListOrderAscending';
    const ORDER_DESCENDING = 'ItemListOrderType.ItemListOrderDescending';
    
    /**
     * ZipPicks classification types
     */
    const CLASSIFICATION_ESSENTIAL = 'Essential';
    const CLASSIFICATION_OUTSTANDING = 'Outstanding';
    const CLASSIFICATION_RECOMMENDED = 'Recommended';
    const CLASSIFICATION_SOLID = 'Solid';
    const CLASSIFICATION_ADEQUATE = 'Adequate';
    
    /**
     * Price range mappings
     */
    const PRICE_RANGES = [
        '$' => '$',
        '$$' => '$$', 
        '$$$' => '$$$',
        '$$$$' => '$$$$'
    ];
    
    /**
     * Cuisine type mappings (Schema.org compliant)
     */
    const CUISINE_TYPES = [
        'american' => 'American',
        'italian' => 'Italian',
        'mexican' => 'Mexican',
        'chinese' => 'Chinese',
        'japanese' => 'Japanese',
        'thai' => 'Thai',
        'indian' => 'Indian',
        'french' => 'French',
        'mediterranean' => 'Mediterranean',
        'greek' => 'Greek',
        'seafood' => 'Seafood',
        'steakhouse' => 'Steakhouse',
        'bbq' => 'Barbecue',
        'pizza' => 'Pizza',
        'burger' => 'Burger',
        'sandwich' => 'Sandwich',
        'coffee' => 'Coffee',
        'bakery' => 'Bakery',
        'dessert' => 'Dessert',
        'vegetarian' => 'Vegetarian',
        'vegan' => 'Vegan'
    ];
    
    /**
     * Get base schema structure
     * 
     * @param string $type Schema.org type
     * @return array Base schema array
     */
    public static function get_base_schema($type) {
        return [
            '@context' => self::CONTEXT,
            '@type' => $type
        ];
    }
    
    /**
     * Get restaurant schema template
     * 
     * @return array Restaurant schema template
     */
    public static function get_restaurant_template() {
        return self::get_base_schema(self::TYPE_RESTAURANT) + [
            self::PROP_NAME => '',
            self::PROP_DESCRIPTION => '',
            self::PROP_URL => '',
            self::PROP_IMAGE => [],
            self::PROP_ADDRESS => [
                '@type' => self::TYPE_POSTAL_ADDRESS,
                self::PROP_STREET_ADDRESS => '',
                self::PROP_ADDRESS_LOCALITY => '',
                self::PROP_ADDRESS_REGION => '',
                self::PROP_POSTAL_CODE => '',
                self::PROP_ADDRESS_COUNTRY => 'US'
            ],
            self::PROP_GEO => [
                '@type' => self::TYPE_GEO_COORDINATES,
                self::PROP_LATITUDE => 0,
                self::PROP_LONGITUDE => 0
            ],
            self::PROP_TELEPHONE => '',
            self::PROP_SERVES_CUISINE => [],
            self::PROP_PRICE_RANGE => '',
            self::PROP_AGGREGATE_RATING => [
                '@type' => self::TYPE_AGGREGATE_RATING,
                self::PROP_RATING_VALUE => 0,
                self::PROP_BEST_RATING => 10,
                self::PROP_WORST_RATING => 0,
                self::PROP_RATING_COUNT => 0
            ]
        ];
    }
    
    /**
     * Get item list schema template
     * 
     * @return array ItemList schema template
     */
    public static function get_item_list_template() {
        return self::get_base_schema(self::TYPE_ITEM_LIST) + [
            self::PROP_NAME => '',
            self::PROP_DESCRIPTION => '',
            self::PROP_URL => '',
            self::PROP_ITEM_LIST_ORDER => self::ORDER_DESCENDING,
            self::PROP_ITEM_LIST_ELEMENT => []
        ];
    }
    
    /**
     * Get list item schema template
     * 
     * @return array ListItem schema template
     */
    public static function get_list_item_template() {
        return [
            '@type' => self::TYPE_LIST_ITEM,
            self::PROP_POSITION => 0,
            self::PROP_ITEM => []
        ];
    }
    
    /**
     * Get organization schema template
     * 
     * @return array Organization schema template
     */
    public static function get_organization_template() {
        return self::get_base_schema(self::TYPE_ORGANIZATION) + [
            self::PROP_NAME => 'ZipPicks',
            self::PROP_DESCRIPTION => 'Local restaurant discovery and critic reviews',
            self::PROP_URL => home_url(),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => ''
            ],
            'sameAs' => []
        ];
    }
    
    /**
     * Get review schema template
     * 
     * @return array Review schema template
     */
    public static function get_review_template() {
        return self::get_base_schema(self::TYPE_REVIEW) + [
            self::PROP_AUTHOR => [
                '@type' => self::TYPE_PERSON,
                self::PROP_NAME => ''
            ],
            self::PROP_REVIEW_RATING => [
                '@type' => self::TYPE_RATING,
                self::PROP_RATING_VALUE => 0,
                self::PROP_BEST_RATING => 10,
                self::PROP_WORST_RATING => 0
            ],
            self::PROP_REVIEW_BODY => '',
            self::PROP_DATE_PUBLISHED => '',
            'itemReviewed' => []
        ];
    }
    
    /**
     * Add ZipPicks extensions to schema
     * 
     * @param array $schema Base schema
     * @param array $extensions ZipPicks data
     * @return array Enhanced schema
     */
    public static function add_zippicks_extensions($schema, $extensions = []) {
        // Master Score
        if (isset($extensions['master_score'])) {
            $schema[self::ZIPPICKS_MASTER_SCORE] = (float) $extensions['master_score'];
        }
        
        // Classification
        if (isset($extensions['classification'])) {
            $schema[self::ZIPPICKS_CLASSIFICATION] = $extensions['classification'];
        }
        
        // Vibes
        if (isset($extensions['vibes']) && is_array($extensions['vibes'])) {
            $schema[self::ZIPPICKS_VIBES] = $extensions['vibes'];
        }
        
        // Taste DNA
        if (isset($extensions['taste_dna'])) {
            $schema[self::ZIPPICKS_TASTE_DNA] = $extensions['taste_dna'];
        }
        
        // Verified Date
        if (isset($extensions['verified_date'])) {
            $schema[self::ZIPPICKS_VERIFIED_DATE] = $extensions['verified_date'];
        }
        
        // Pillar Scores
        if (isset($extensions['pillar_scores']) && is_array($extensions['pillar_scores'])) {
            $schema[self::ZIPPICKS_PILLAR_SCORES] = $extensions['pillar_scores'];
        }
        
        // Generation metadata
        if (isset($extensions['generation_id'])) {
            $schema[self::ZIPPICKS_GENERATION_ID] = (int) $extensions['generation_id'];
        }
        
        if (isset($extensions['ai_provider'])) {
            $schema[self::ZIPPICKS_AI_PROVIDER] = $extensions['ai_provider'];
        }
        
        // ZipBusiness ID
        if (isset($extensions['zpid'])) {
            $schema[self::ZIPPICKS_ZPID] = $extensions['zpid'];
        }
        
        // List context
        if (isset($extensions['list_type'])) {
            $schema[self::ZIPPICKS_LIST_TYPE] = $extensions['list_type'];
        }
        
        if (isset($extensions['city_context'])) {
            $schema[self::ZIPPICKS_CITY_CONTEXT] = $extensions['city_context'];
        }
        
        return $schema;
    }
    
    /**
     * Validate schema type
     * 
     * @param string $type Schema type to validate
     * @return bool True if valid
     */
    public static function is_valid_type($type) {
        $valid_types = [
            self::TYPE_RESTAURANT,
            self::TYPE_LOCAL_BUSINESS,
            self::TYPE_ITEM_LIST,
            self::TYPE_LIST_ITEM,
            self::TYPE_REVIEW,
            self::TYPE_AGGREGATE_RATING,
            self::TYPE_RATING,
            self::TYPE_ORGANIZATION,
            self::TYPE_PERSON,
            self::TYPE_POSTAL_ADDRESS,
            self::TYPE_GEO_COORDINATES
        ];
        
        return in_array($type, $valid_types, true);
    }
    
    /**
     * Get classification from score
     * 
     * @param float $score Score from 0-10
     * @return string Classification
     */
    public static function get_classification_from_score($score) {
        if ($score >= 9.0) {
            return self::CLASSIFICATION_ESSENTIAL;
        } elseif ($score >= 8.0) {
            return self::CLASSIFICATION_OUTSTANDING;
        } elseif ($score >= 7.0) {
            return self::CLASSIFICATION_RECOMMENDED;
        } elseif ($score >= 6.0) {
            return self::CLASSIFICATION_SOLID;
        } else {
            return self::CLASSIFICATION_ADEQUATE;
        }
    }
    
    /**
     * Normalize cuisine type to Schema.org format
     * 
     * @param string $cuisine Raw cuisine type
     * @return string Normalized cuisine
     */
    public static function normalize_cuisine($cuisine) {
        $cuisine = strtolower(trim($cuisine));
        return self::CUISINE_TYPES[$cuisine] ?? ucfirst($cuisine);
    }
    
    /**
     * Format address for schema
     * 
     * @param array $address_parts Address components
     * @return array Schema.org PostalAddress
     */
    public static function format_address($address_parts) {
        return [
            '@type' => self::TYPE_POSTAL_ADDRESS,
            self::PROP_STREET_ADDRESS => $address_parts['street'] ?? '',
            self::PROP_ADDRESS_LOCALITY => $address_parts['city'] ?? '',
            self::PROP_ADDRESS_REGION => $address_parts['state'] ?? '',
            self::PROP_POSTAL_CODE => $address_parts['zip'] ?? '',
            self::PROP_ADDRESS_COUNTRY => $address_parts['country'] ?? 'US'
        ];
    }
    
    /**
     * Format coordinates for schema
     * 
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @return array Schema.org GeoCoordinates
     */
    public static function format_coordinates($lat, $lng) {
        return [
            '@type' => self::TYPE_GEO_COORDINATES,
            self::PROP_LATITUDE => (float) $lat,
            self::PROP_LONGITUDE => (float) $lng
        ];
    }
    
    /**
     * Format rating for schema
     * 
     * @param float $value Rating value
     * @param int $count Rating count
     * @param float $best Best possible rating (default 10)
     * @param float $worst Worst possible rating (default 0)
     * @return array Schema.org AggregateRating
     */
    public static function format_aggregate_rating($value, $count = 0, $best = 10, $worst = 0) {
        return [
            '@type' => self::TYPE_AGGREGATE_RATING,
            self::PROP_RATING_VALUE => (float) $value,
            self::PROP_BEST_RATING => (float) $best,
            self::PROP_WORST_RATING => (float) $worst,
            self::PROP_RATING_COUNT => (int) $count
        ];
    }
    
    /**
     * Get required properties for schema type
     * 
     * @param string $type Schema.org type
     * @return array Required properties
     */
    public static function get_required_properties($type) {
        switch ($type) {
            case self::TYPE_RESTAURANT:
            case self::TYPE_LOCAL_BUSINESS:
                return [self::PROP_NAME, self::PROP_ADDRESS];
                
            case self::TYPE_ITEM_LIST:
                return [self::PROP_NAME, self::PROP_ITEM_LIST_ELEMENT];
                
            case self::TYPE_REVIEW:
                return [self::PROP_AUTHOR, self::PROP_REVIEW_RATING, 'itemReviewed'];
                
            case self::TYPE_AGGREGATE_RATING:
                return [self::PROP_RATING_VALUE];
                
            default:
                return [self::PROP_NAME];
        }
    }
}