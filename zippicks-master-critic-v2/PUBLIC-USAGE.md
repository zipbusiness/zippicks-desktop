# Master Critic Public Usage Guide

## Overview

The Master Critic Public class provides frontend functionality for displaying master restaurant sets using a shortcode system.

## Shortcode Usage

### Basic Usage
```
[master_critic_list id="1"]
```

### With Options
```
[master_critic_list id="1" show_scores="true" show_summaries="true" show_price_tiers="true" show_neighborhoods="false"]
```

## Shortcode Parameters

- `id` (required): The master set ID to display
- `show_scores`: Show/hide restaurant scores (default: true)
- `show_summaries`: Show/hide restaurant summaries (default: true)  
- `show_price_tiers`: Show/hide price tier indicators (default: true)
- `show_neighborhoods`: Show/hide neighborhood information (default: false)

## Features

### Automatic Grouping
Restaurants are automatically grouped by tier:
- **Essential**: Top-tier must-visit restaurants
- **Notable**: Great restaurants worth trying
- **Worthy**: Good options for specific occasions
- **Other**: Any restaurants not in the above tiers

### Responsive Design
- Mobile-friendly layout
- Responsive grid system
- Touch-friendly interface

### Performance Optimized
- CSS/JS only loaded when shortcode is present on page
- Minimal inline assets for fast loading
- No external dependencies

### Schema Integration
- Automatic Schema.org structured data output
- Integrated with existing schema system
- SEO-friendly markup

## Database Requirements

The public class expects the following database structure:
- Master sets table with published status
- Master items table with active status
- Proper tier groupings (Essential, Notable, Worthy)

## Error Handling

The shortcode gracefully handles:
- Missing or invalid set IDs
- Sets not in "published" status
- Empty result sets
- Database connection issues

## Styling

### CSS Classes

- `.zpmc-master-list`: Main container
- `.zpmc-header`: List header section
- `.zpmc-tier-group`: Tier group containers
- `.zpmc-restaurant`: Individual restaurant cards
- `.zpmc-score`: Score displays
- `.zpmc-price-tier`: Price tier indicators

### Customization

To customize styling, add CSS rules to your theme:

```css
.zpmc-master-list {
    /* Your custom styles */
}

.zpmc-restaurant {
    /* Restaurant card styling */
}
```

## Integration Notes

1. **Assets**: CSS and JS are automatically enqueued only on pages containing the shortcode
2. **Schema**: Schema.org markup is automatically added to pages with the shortcode
3. **Caching**: Compatible with WordPress caching plugins
4. **SEO**: Generates proper structured data for search engines

## Testing

Use the included test file: `test-public-shortcode.php` to verify functionality.

## Error Messages

- "Error: Master set ID is required": No ID parameter provided
- "Error: Master set not found or not published": Invalid ID or set not published
- "No restaurants found in this master set": Set exists but has no active items

## Performance Notes

- Styles are inlined for optimal performance
- Minimal JavaScript for enhanced functionality
- Database queries are optimized
- No external asset dependencies