<?php
/**
 * Schema Validator
 * 
 * Validates Schema.org structured data for Google compliance
 *
 * @package ZipPicks_Schema
 * @since 1.0.0
 */

class ZipPicks_Schema_Validator {
    
    private $errors = [];
    private $warnings = [];
    
    /**
     * Validate schema data
     * 
     * @param array $schema Schema data to validate
     * @return array Validation result with status, errors, warnings
     */
    public function validate($schema) {
        $this->errors = [];
        $this->warnings = [];
        
        if (!is_array($schema)) {
            $this->errors[] = 'Schema must be an array';
            return $this->get_validation_result();
        }
        
        // Validate basic structure
        $this->validate_basic_structure($schema);
        
        // Validate based on type
        if (isset($schema['@type'])) {
            $this->validate_by_type($schema, $schema['@type']);
        }
        
        // Validate common properties
        $this->validate_common_properties($schema);
        
        // Validate ZipPicks extensions
        $this->validate_zippicks_extensions($schema);
        
        return $this->get_validation_result();
    }
    
    /**
     * Validate basic schema structure
     * 
     * @param array $schema Schema data
     */
    private function validate_basic_structure($schema) {
        // Check for @context
        if (!isset($schema['@context'])) {
            $this->errors[] = 'Missing required @context property';
        } elseif ($schema['@context'] !== ZipPicks_Schema_Types::CONTEXT) {
            $this->errors[] = 'Invalid @context value';
        }
        
        // Check for @type
        if (!isset($schema['@type'])) {
            $this->errors[] = 'Missing required @type property';
        } elseif (!ZipPicks_Schema_Types::is_valid_type($schema['@type'])) {
            $this->warnings[] = 'Unknown schema type: ' . $schema['@type'];
        }
    }
    
    /**
     * Validate schema based on type
     * 
     * @param array $schema Schema data
     * @param string $type Schema type
     */
    private function validate_by_type($schema, $type) {
        switch ($type) {
            case ZipPicks_Schema_Types::TYPE_RESTAURANT:
            case ZipPicks_Schema_Types::TYPE_LOCAL_BUSINESS:
                $this->validate_business_schema($schema);
                break;
                
            case ZipPicks_Schema_Types::TYPE_ITEM_LIST:
                $this->validate_item_list_schema($schema);
                break;
                
            case ZipPicks_Schema_Types::TYPE_REVIEW:
                $this->validate_review_schema($schema);
                break;
                
            case ZipPicks_Schema_Types::TYPE_AGGREGATE_RATING:
                $this->validate_rating_schema($schema);
                break;
                
            case ZipPicks_Schema_Types::TYPE_ORGANIZATION:
                $this->validate_organization_schema($schema);
                break;
        }
    }
    
    /**
     * Validate business/restaurant schema
     * 
     * @param array $schema Schema data
     */
    private function validate_business_schema($schema) {
        // Required properties
        $required = [
            ZipPicks_Schema_Types::PROP_NAME => 'Business name is required',
            ZipPicks_Schema_Types::PROP_ADDRESS => 'Business address is required'
        ];
        
        foreach ($required as $prop => $message) {
            if (!isset($schema[$prop]) || empty($schema[$prop])) {
                $this->errors[] = $message;
            }
        }
        
        // Validate address if present
        if (isset($schema[ZipPicks_Schema_Types::PROP_ADDRESS])) {
            $this->validate_address($schema[ZipPicks_Schema_Types::PROP_ADDRESS]);
        }
        
        // Validate coordinates if present
        if (isset($schema[ZipPicks_Schema_Types::PROP_GEO])) {
            $this->validate_coordinates($schema[ZipPicks_Schema_Types::PROP_GEO]);
        }
        
        // Validate rating if present
        if (isset($schema[ZipPicks_Schema_Types::PROP_AGGREGATE_RATING])) {
            $this->validate_rating_schema($schema[ZipPicks_Schema_Types::PROP_AGGREGATE_RATING]);
        }
        
        // Validate price range
        if (isset($schema[ZipPicks_Schema_Types::PROP_PRICE_RANGE])) {
            $this->validate_price_range($schema[ZipPicks_Schema_Types::PROP_PRICE_RANGE]);
        }
        
        // Validate cuisine
        if (isset($schema[ZipPicks_Schema_Types::PROP_SERVES_CUISINE])) {
            $this->validate_cuisine($schema[ZipPicks_Schema_Types::PROP_SERVES_CUISINE]);
        }
        
        // Validate telephone
        if (isset($schema[ZipPicks_Schema_Types::PROP_TELEPHONE])) {
            $this->validate_telephone($schema[ZipPicks_Schema_Types::PROP_TELEPHONE]);
        }
        
        // Validate URL
        if (isset($schema[ZipPicks_Schema_Types::PROP_URL])) {
            $this->validate_url($schema[ZipPicks_Schema_Types::PROP_URL]);
        }
        
        // Validate images
        if (isset($schema[ZipPicks_Schema_Types::PROP_IMAGE])) {
            $this->validate_images($schema[ZipPicks_Schema_Types::PROP_IMAGE]);
        }
    }
    
    /**
     * Validate item list schema
     * 
     * @param array $schema Schema data
     */
    private function validate_item_list_schema($schema) {
        // Required properties
        if (!isset($schema[ZipPicks_Schema_Types::PROP_NAME]) || empty($schema[ZipPicks_Schema_Types::PROP_NAME])) {
            $this->errors[] = 'List name is required';
        }
        
        if (!isset($schema[ZipPicks_Schema_Types::PROP_ITEM_LIST_ELEMENT])) {
            $this->errors[] = 'List items are required';
        } elseif (!is_array($schema[ZipPicks_Schema_Types::PROP_ITEM_LIST_ELEMENT])) {
            $this->errors[] = 'List items must be an array';
        } elseif (empty($schema[ZipPicks_Schema_Types::PROP_ITEM_LIST_ELEMENT])) {
            $this->warnings[] = 'List has no items';
        } else {
            // Validate each list item
            foreach ($schema[ZipPicks_Schema_Types::PROP_ITEM_LIST_ELEMENT] as $index => $item) {
                $this->validate_list_item($item, $index);
            }
        }
        
        // Validate list order
        if (isset($schema[ZipPicks_Schema_Types::PROP_ITEM_LIST_ORDER])) {
            $valid_orders = [
                ZipPicks_Schema_Types::ORDER_UNORDERED,
                ZipPicks_Schema_Types::ORDER_ASCENDING,
                ZipPicks_Schema_Types::ORDER_DESCENDING
            ];
            
            if (!in_array($schema[ZipPicks_Schema_Types::PROP_ITEM_LIST_ORDER], $valid_orders)) {
                $this->warnings[] = 'Invalid itemListOrder value';
            }
        }
        
        // Validate URL
        if (isset($schema[ZipPicks_Schema_Types::PROP_URL])) {
            $this->validate_url($schema[ZipPicks_Schema_Types::PROP_URL]);
        }
    }
    
    /**
     * Validate list item
     * 
     * @param array $item List item data
     * @param int $index Item index for error reporting
     */
    private function validate_list_item($item, $index) {
        if (!is_array($item)) {
            $this->errors[] = "List item {$index} must be an array";
            return;
        }
        
        // Check @type
        if (!isset($item['@type']) || $item['@type'] !== ZipPicks_Schema_Types::TYPE_LIST_ITEM) {
            $this->errors[] = "List item {$index} must have @type 'ListItem'";
        }
        
        // Check position
        if (!isset($item[ZipPicks_Schema_Types::PROP_POSITION])) {
            $this->errors[] = "List item {$index} missing position property";
        } elseif (!is_numeric($item[ZipPicks_Schema_Types::PROP_POSITION])) {
            $this->errors[] = "List item {$index} position must be numeric";
        }
        
        // Check item property
        if (!isset($item[ZipPicks_Schema_Types::PROP_ITEM])) {
            $this->errors[] = "List item {$index} missing item property";
        } elseif (is_array($item[ZipPicks_Schema_Types::PROP_ITEM])) {
            // Validate nested item schema
            $nested_validation = $this->validate($item[ZipPicks_Schema_Types::PROP_ITEM]);
            if (isset($nested_validation['valid']) && !$nested_validation['valid']) {
                if (isset($nested_validation['errors']) && is_array($nested_validation['errors'])) {
                    foreach ($nested_validation['errors'] as $error) {
                        $this->errors[] = "List item {$index}: {$error}";
                    }
                }
            }
        }
    }
    
    /**
     * Validate review schema
     * 
     * @param array $schema Schema data
     */
    private function validate_review_schema($schema) {
        // Required properties
        $required = [
            ZipPicks_Schema_Types::PROP_AUTHOR => 'Review author is required',
            ZipPicks_Schema_Types::PROP_REVIEW_RATING => 'Review rating is required',
            'itemReviewed' => 'Reviewed item is required'
        ];
        
        foreach ($required as $prop => $message) {
            if (!isset($schema[$prop]) || empty($schema[$prop])) {
                $this->errors[] = $message;
            }
        }
        
        // Validate author
        if (isset($schema[ZipPicks_Schema_Types::PROP_AUTHOR])) {
            $this->validate_person($schema[ZipPicks_Schema_Types::PROP_AUTHOR]);
        }
        
        // Validate rating
        if (isset($schema[ZipPicks_Schema_Types::PROP_REVIEW_RATING])) {
            $this->validate_rating_schema($schema[ZipPicks_Schema_Types::PROP_REVIEW_RATING]);
        }
        
        // Validate dates
        if (isset($schema[ZipPicks_Schema_Types::PROP_DATE_PUBLISHED])) {
            $this->validate_date($schema[ZipPicks_Schema_Types::PROP_DATE_PUBLISHED]);
        }
        
        if (isset($schema[ZipPicks_Schema_Types::PROP_DATE_MODIFIED])) {
            $this->validate_date($schema[ZipPicks_Schema_Types::PROP_DATE_MODIFIED]);
        }
    }
    
    /**
     * Validate rating schema
     * 
     * @param array $schema Schema data
     */
    private function validate_rating_schema($schema) {
        if (!is_array($schema)) {
            $this->errors[] = 'Rating must be an array';
            return;
        }
        
        // Check @type
        $valid_rating_types = [
            ZipPicks_Schema_Types::TYPE_RATING,
            ZipPicks_Schema_Types::TYPE_AGGREGATE_RATING
        ];
        
        if (!isset($schema['@type']) || !in_array($schema['@type'], $valid_rating_types)) {
            $this->errors[] = 'Invalid rating type';
        }
        
        // Required properties
        if (!isset($schema[ZipPicks_Schema_Types::PROP_RATING_VALUE])) {
            $this->errors[] = 'Rating value is required';
        } elseif (!is_numeric($schema[ZipPicks_Schema_Types::PROP_RATING_VALUE])) {
            $this->errors[] = 'Rating value must be numeric';
        } else {
            $value = (float) $schema[ZipPicks_Schema_Types::PROP_RATING_VALUE];
            $best = isset($schema[ZipPicks_Schema_Types::PROP_BEST_RATING]) ? 
                   (float) $schema[ZipPicks_Schema_Types::PROP_BEST_RATING] : 10;
            $worst = isset($schema[ZipPicks_Schema_Types::PROP_WORST_RATING]) ? 
                    (float) $schema[ZipPicks_Schema_Types::PROP_WORST_RATING] : 0;
            
            if ($value < $worst || $value > $best) {
                $this->errors[] = "Rating value ({$value}) outside valid range ({$worst}-{$best})";
            }
        }
        
        // Validate aggregate rating specific properties
        if ($schema['@type'] === ZipPicks_Schema_Types::TYPE_AGGREGATE_RATING) {
            if (isset($schema[ZipPicks_Schema_Types::PROP_RATING_COUNT])) {
                if (!is_numeric($schema[ZipPicks_Schema_Types::PROP_RATING_COUNT])) {
                    $this->errors[] = 'Rating count must be numeric';
                } elseif ((int) $schema[ZipPicks_Schema_Types::PROP_RATING_COUNT] < 0) {
                    $this->errors[] = 'Rating count cannot be negative';
                }
            }
        }
    }
    
    /**
     * Validate organization schema
     * 
     * @param array $schema Schema data
     */
    private function validate_organization_schema($schema) {
        if (!isset($schema[ZipPicks_Schema_Types::PROP_NAME]) || empty($schema[ZipPicks_Schema_Types::PROP_NAME])) {
            $this->errors[] = 'Organization name is required';
        }
        
        if (isset($schema[ZipPicks_Schema_Types::PROP_URL])) {
            $this->validate_url($schema[ZipPicks_Schema_Types::PROP_URL]);
        }
        
        if (isset($schema['logo'])) {
            $this->validate_image_object($schema['logo']);
        }
        
        if (isset($schema['sameAs'])) {
            if (!is_array($schema['sameAs'])) {
                $this->errors[] = 'sameAs must be an array';
            } else {
                foreach ($schema['sameAs'] as $url) {
                    $this->validate_url($url);
                }
            }
        }
    }
    
    /**
     * Validate common properties
     * 
     * @param array $schema Schema data
     */
    private function validate_common_properties($schema) {
        // Name validation
        if (isset($schema[ZipPicks_Schema_Types::PROP_NAME])) {
            if (!is_string($schema[ZipPicks_Schema_Types::PROP_NAME]) || 
                empty(trim($schema[ZipPicks_Schema_Types::PROP_NAME]))) {
                $this->errors[] = 'Name must be a non-empty string';
            }
        }
        
        // Description validation
        if (isset($schema[ZipPicks_Schema_Types::PROP_DESCRIPTION])) {
            if (!is_string($schema[ZipPicks_Schema_Types::PROP_DESCRIPTION])) {
                $this->errors[] = 'Description must be a string';
            } elseif (strlen($schema[ZipPicks_Schema_Types::PROP_DESCRIPTION]) > 300) {
                $this->warnings[] = 'Description is quite long (>300 characters)';
            }
        }
    }
    
    /**
     * Validate ZipPicks extensions
     * 
     * @param array $schema Schema data
     */
    private function validate_zippicks_extensions($schema) {
        // Master score validation
        if (isset($schema[ZipPicks_Schema_Types::ZIPPICKS_MASTER_SCORE])) {
            $score = $schema[ZipPicks_Schema_Types::ZIPPICKS_MASTER_SCORE];
            if (!is_numeric($score)) {
                $this->errors[] = 'ZipPicks master score must be numeric';
            } elseif ($score < 0 || $score > 10) {
                $this->errors[] = 'ZipPicks master score must be between 0 and 10';
            }
        }
        
        // Classification validation
        if (isset($schema[ZipPicks_Schema_Types::ZIPPICKS_CLASSIFICATION])) {
            $valid_classifications = [
                ZipPicks_Schema_Types::CLASSIFICATION_ESSENTIAL,
                ZipPicks_Schema_Types::CLASSIFICATION_OUTSTANDING,
                ZipPicks_Schema_Types::CLASSIFICATION_RECOMMENDED,
                ZipPicks_Schema_Types::CLASSIFICATION_SOLID,
                ZipPicks_Schema_Types::CLASSIFICATION_ADEQUATE
            ];
            
            if (!in_array($schema[ZipPicks_Schema_Types::ZIPPICKS_CLASSIFICATION], $valid_classifications)) {
                $this->warnings[] = 'Unknown ZipPicks classification';
            }
        }
        
        // Vibes validation
        if (isset($schema[ZipPicks_Schema_Types::ZIPPICKS_VIBES])) {
            if (!is_array($schema[ZipPicks_Schema_Types::ZIPPICKS_VIBES])) {
                $this->errors[] = 'ZipPicks vibes must be an array';
            }
        }
        
        // Pillar scores validation
        if (isset($schema[ZipPicks_Schema_Types::ZIPPICKS_PILLAR_SCORES])) {
            $pillar_scores = $schema[ZipPicks_Schema_Types::ZIPPICKS_PILLAR_SCORES];
            if (!is_array($pillar_scores)) {
                $this->errors[] = 'ZipPicks pillar scores must be an array';
            } else {
                $expected_pillars = ['taste', 'service', 'speed', 'value', 'overall'];
                foreach ($expected_pillars as $pillar) {
                    if (isset($pillar_scores[$pillar]) && !is_numeric($pillar_scores[$pillar])) {
                        $this->errors[] = "Pillar score for '{$pillar}' must be numeric";
                    }
                }
            }
        }
        
        // Generation ID validation
        if (isset($schema[ZipPicks_Schema_Types::ZIPPICKS_GENERATION_ID])) {
            if (!is_integer($schema[ZipPicks_Schema_Types::ZIPPICKS_GENERATION_ID])) {
                $this->errors[] = 'ZipPicks generation ID must be an integer';
            }
        }
    }
    
    /**
     * Validate address
     * 
     * @param array $address Address data
     */
    private function validate_address($address) {
        if (!is_array($address)) {
            $this->errors[] = 'Address must be an array';
            return;
        }
        
        if (!isset($address['@type']) || $address['@type'] !== ZipPicks_Schema_Types::TYPE_POSTAL_ADDRESS) {
            $this->errors[] = 'Address must have @type PostalAddress';
        }
        
        // Street address is most important for local businesses
        if (!isset($address[ZipPicks_Schema_Types::PROP_STREET_ADDRESS]) || 
            empty($address[ZipPicks_Schema_Types::PROP_STREET_ADDRESS])) {
            $this->warnings[] = 'Street address is recommended for better local SEO';
        }
        
        // Locality (city) is also important
        if (!isset($address[ZipPicks_Schema_Types::PROP_ADDRESS_LOCALITY]) || 
            empty($address[ZipPicks_Schema_Types::PROP_ADDRESS_LOCALITY])) {
            $this->warnings[] = 'City/locality is recommended for better local SEO';
        }
    }
    
    /**
     * Validate coordinates
     * 
     * @param array $geo Coordinates data
     */
    private function validate_coordinates($geo) {
        if (!is_array($geo)) {
            $this->errors[] = 'Coordinates must be an array';
            return;
        }
        
        if (!isset($geo['@type']) || $geo['@type'] !== ZipPicks_Schema_Types::TYPE_GEO_COORDINATES) {
            $this->errors[] = 'Coordinates must have @type GeoCoordinates';
        }
        
        if (!isset($geo[ZipPicks_Schema_Types::PROP_LATITUDE]) || 
            !is_numeric($geo[ZipPicks_Schema_Types::PROP_LATITUDE])) {
            $this->errors[] = 'Valid latitude is required';
        } else {
            $lat = (float) $geo[ZipPicks_Schema_Types::PROP_LATITUDE];
            if ($lat < -90 || $lat > 90) {
                $this->errors[] = 'Latitude must be between -90 and 90';
            }
        }
        
        if (!isset($geo[ZipPicks_Schema_Types::PROP_LONGITUDE]) || 
            !is_numeric($geo[ZipPicks_Schema_Types::PROP_LONGITUDE])) {
            $this->errors[] = 'Valid longitude is required';
        } else {
            $lng = (float) $geo[ZipPicks_Schema_Types::PROP_LONGITUDE];
            if ($lng < -180 || $lng > 180) {
                $this->errors[] = 'Longitude must be between -180 and 180';
            }
        }
    }
    
    /**
     * Validate person
     * 
     * @param array $person Person data
     */
    private function validate_person($person) {
        if (!is_array($person)) {
            $this->errors[] = 'Person must be an array';
            return;
        }
        
        if (!isset($person['@type']) || $person['@type'] !== ZipPicks_Schema_Types::TYPE_PERSON) {
            $this->errors[] = 'Person must have @type Person';
        }
        
        if (!isset($person[ZipPicks_Schema_Types::PROP_NAME]) || 
            empty($person[ZipPicks_Schema_Types::PROP_NAME])) {
            $this->errors[] = 'Person name is required';
        }
    }
    
    /**
     * Validate price range
     * 
     * @param string $price_range Price range value
     */
    private function validate_price_range($price_range) {
        $valid_ranges = array_values(ZipPicks_Schema_Types::PRICE_RANGES);
        if (!in_array($price_range, $valid_ranges)) {
            $this->warnings[] = 'Unusual price range format: ' . $price_range;
        }
    }
    
    /**
     * Validate cuisine
     * 
     * @param array $cuisine Cuisine array
     */
    private function validate_cuisine($cuisine) {
        if (!is_array($cuisine)) {
            $this->errors[] = 'Cuisine must be an array';
            return;
        }
        
        if (empty($cuisine)) {
            $this->warnings[] = 'No cuisine types specified';
        }
        
        foreach ($cuisine as $type) {
            if (!is_string($type) || empty(trim($type))) {
                $this->errors[] = 'Cuisine type must be a non-empty string';
            }
        }
    }
    
    /**
     * Validate telephone
     * 
     * @param string $phone Phone number
     */
    private function validate_telephone($phone) {
        if (!is_string($phone)) {
            $this->errors[] = 'Telephone must be a string';
            return;
        }
        
        // Basic phone number format check
        if (!preg_match('/[\d\s\-\(\)\+\.]+/', $phone)) {
            $this->warnings[] = 'Phone number format may not be valid';
        }
    }
    
    /**
     * Validate URL
     * 
     * @param string $url URL string
     */
    private function validate_url($url) {
        if (!is_string($url)) {
            $this->errors[] = 'URL must be a string';
            return;
        }
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->errors[] = 'Invalid URL format: ' . $url;
        }
    }
    
    /**
     * Validate images
     * 
     * @param array $images Image array
     */
    private function validate_images($images) {
        if (!is_array($images)) {
            $this->errors[] = 'Images must be an array';
            return;
        }
        
        foreach ($images as $index => $image) {
            if (is_string($image)) {
                $this->validate_url($image);
            } elseif (is_array($image)) {
                $this->validate_image_object($image, $index);
            } else {
                $this->errors[] = "Image {$index} must be URL string or ImageObject";
            }
        }
    }
    
    /**
     * Validate image object
     * 
     * @param array $image Image object
     * @param int $index Image index for error reporting
     */
    private function validate_image_object($image, $index = null) {
        $prefix = $index !== null ? "Image {$index}: " : "Image: ";
        
        if (!is_array($image)) {
            $this->errors[] = $prefix . 'must be an array';
            return;
        }
        
        if (isset($image['@type']) && $image['@type'] !== 'ImageObject') {
            $this->errors[] = $prefix . 'must have @type ImageObject';
        }
        
        if (!isset($image['url']) || empty($image['url'])) {
            $this->errors[] = $prefix . 'URL is required';
        } else {
            $this->validate_url($image['url']);
        }
    }
    
    /**
     * Validate date string
     * 
     * @param string $date Date string
     */
    private function validate_date($date) {
        if (!is_string($date)) {
            $this->errors[] = 'Date must be a string';
            return;
        }
        
        // Try to parse as ISO 8601 date
        $parsed = DateTime::createFromFormat(DateTime::ATOM, $date);
        if (!$parsed) {
            // Try other common formats
            $formats = ['Y-m-d', 'Y-m-d H:i:s', 'c'];
            $valid = false;
            
            foreach ($formats as $format) {
                $parsed = DateTime::createFromFormat($format, $date);
                if ($parsed) {
                    $valid = true;
                    break;
                }
            }
            
            if (!$valid) {
                $this->warnings[] = 'Date format may not be valid: ' . $date;
            }
        }
    }
    
    /**
     * Get validation result
     * 
     * @return array Validation result
     */
    private function get_validation_result() {
        return [
            'valid' => empty($this->errors),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'error_count' => count($this->errors),
            'warning_count' => count($this->warnings)
        ];
    }
    
    /**
     * Test schema with Google's Rich Results Test (simulation)
     * 
     * @param array $schema Schema data
     * @return array Test result
     */
    public function test_with_google($schema) {
        $validation = $this->validate($schema);
        
        // Simulate Google's additional checks
        $google_issues = [];
        
        // Check for recommended properties based on type
        if (isset($schema['@type'])) {
            switch ($schema['@type']) {
                case ZipPicks_Schema_Types::TYPE_RESTAURANT:
                    $recommended = [
                        ZipPicks_Schema_Types::PROP_IMAGE => 'Image recommended for rich results',
                        ZipPicks_Schema_Types::PROP_AGGREGATE_RATING => 'Rating recommended for rich results',
                        ZipPicks_Schema_Types::PROP_PRICE_RANGE => 'Price range helps with user intent'
                    ];
                    
                    foreach ($recommended as $prop => $message) {
                        if (!isset($schema[$prop])) {
                            $google_issues[] = $message;
                        }
                    }
                    break;
                    
                case ZipPicks_Schema_Types::TYPE_ITEM_LIST:
                    if (!isset($schema[ZipPicks_Schema_Types::PROP_IMAGE])) {
                        $google_issues[] = 'Image recommended for list rich results';
                    }
                    break;
            }
        }
        
        return [
            'validation' => $validation,
            'google_issues' => $google_issues,
            'eligible_for_rich_results' => $validation['valid'] && empty($google_issues)
        ];
    }
}