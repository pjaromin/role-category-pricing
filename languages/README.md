# Translation Guide for WooCommerce Role Category Pricing

This directory contains translation files for the WooCommerce Role Category Pricing plugin.

## Available Translations

- **English (en_US)**: Default language (built into the plugin)
- **Spanish (es_ES)**: Spanish (Spain) translation

## How to Contribute Translations

### Method 1: Using Poedit (Recommended)

1. Download and install [Poedit](https://poedit.net/)
2. Open the `woocommerce-role-category-pricing.pot` template file
3. Create a new translation by selecting your language
4. Translate all strings
5. Save the file as `woocommerce-role-category-pricing-{locale}.po`
6. Poedit will automatically generate the `.mo` file

### Method 2: Manual Translation

1. Copy the `woocommerce-role-category-pricing.pot` file
2. Rename it to `woocommerce-role-category-pricing-{locale}.po` (e.g., `woocommerce-role-category-pricing-fr_FR.po` for French)
3. Edit the header information:
   - Update the `Language` field
   - Update the `Language-Team` field
   - Set the `PO-Revision-Date`
4. Translate each `msgid` by filling in the corresponding `msgstr`
5. Generate the `.mo` file using a tool like `msgfmt` or Poedit

### Locale Codes

Common locale codes:
- `es_ES` - Spanish (Spain)
- `fr_FR` - French (France)
- `de_DE` - German (Germany)
- `it_IT` - Italian (Italy)
- `pt_BR` - Portuguese (Brazil)
- `ru_RU` - Russian (Russia)
- `ja` - Japanese
- `zh_CN` - Chinese (Simplified)
- `ar` - Arabic

## File Structure

- `woocommerce-role-category-pricing.pot` - Translation template (do not modify)
- `woocommerce-role-category-pricing-{locale}.po` - Translation source files
- `woocommerce-role-category-pricing-{locale}.mo` - Compiled translation files (binary)
- `generate-pot.php` - Script to regenerate the POT template

## Translation Context

### Key Terms

- **Role**: User role in WordPress (e.g., Customer, Subscriber)
- **Category**: WooCommerce product category
- **Discount**: Price reduction percentage
- **Wholesale**: Bulk/trade pricing (usually excluded from this plugin)
- **Base Discount**: Default discount applied when no category-specific discount exists

### Important Notes

1. **Preserve placeholders**: Keep `%s`, `%d`, and similar placeholders in their original positions
2. **HTML tags**: Maintain any HTML tags in the translations
3. **Context**: Consider the UI context when translating (button labels, form fields, etc.)
4. **Consistency**: Use consistent terminology throughout the translation

## Testing Translations

1. Place your `.po` and `.mo` files in the `languages` directory
2. Change your WordPress site language to match your translation
3. Navigate to the plugin's admin pages to verify the translation
4. Test all admin interfaces: Role Configuration, Category Discounts, Import/Export

## RTL (Right-to-Left) Languages

The plugin includes RTL support for languages like Arabic and Hebrew:
- RTL-specific CSS is automatically loaded for RTL languages
- Admin interface elements are properly aligned for RTL reading
- Form fields and tables adjust their layout automatically

## Submitting Translations

To contribute your translation:

1. Fork the plugin repository
2. Add your translation files to the `languages` directory
3. Test the translation thoroughly
4. Submit a pull request with:
   - The `.po` source file
   - The `.mo` compiled file
   - A brief description of what was translated

## Updating Translations

When the plugin is updated:

1. Check if new strings were added to the `.pot` file
2. Update your `.po` file with the new strings
3. Translate the new strings
4. Recompile the `.mo` file

## Translation Tools

### Recommended Tools
- [Poedit](https://poedit.net/) - Cross-platform translation editor
- [Loco Translate](https://wordpress.org/plugins/loco-translate/) - WordPress plugin for in-browser translation
- [WPML](https://wpml.org/) - Professional translation management

### Command Line Tools
```bash
# Extract strings (if you have wp-cli)
wp i18n make-pot . languages/woocommerce-role-category-pricing.pot

# Compile .po to .mo
msgfmt woocommerce-role-category-pricing-es_ES.po -o woocommerce-role-category-pricing-es_ES.mo
```

## Support

If you need help with translations or encounter issues:

1. Check the [WordPress Codex on Internationalization](https://codex.wordpress.org/I18n_for_WordPress_Developers)
2. Open an issue in the plugin repository
3. Contact the plugin maintainers

Thank you for contributing to make this plugin accessible to users worldwide!