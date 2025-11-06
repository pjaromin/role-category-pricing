<?php
/**
 * Simple script to generate translation template file
 * This is a basic implementation - for production use, consider using wp-cli or poedit
 *
 * @package WRCP
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Extract translatable strings from PHP files
 */
function wrcp_extract_translatable_strings() {
    $plugin_dir = dirname(__DIR__);
    $strings = array();
    
    // Directories to scan
    $scan_dirs = array(
        $plugin_dir,
        $plugin_dir . '/includes',
        $plugin_dir . '/admin/views'
    );
    
    // Translation functions to look for
    $functions = array('__', '_e', '_n', '_x', '_ex', '_nx', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e');
    
    foreach ($scan_dirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        
        $files = glob($dir . '/*.php');
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            
            // Simple regex to find translation function calls
            foreach ($functions as $func) {
                $pattern = '/' . preg_quote($func) . '\s*\(\s*[\'"]([^\'"]*)[\'"].*?[\'"]woocommerce-role-category-pricing[\'"].*?\)/';
                preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
                
                foreach ($matches as $match) {
                    $string = $match[1];
                    $relative_file = str_replace($plugin_dir . '/', '', $file);
                    
                    if (!isset($strings[$string])) {
                        $strings[$string] = array();
                    }
                    
                    $strings[$string][] = $relative_file;
                }
            }
        }
    }
    
    return $strings;
}

/**
 * Generate POT file content
 */
function wrcp_generate_pot_content($strings) {
    $pot_content = '';
    
    // Add header
    $pot_content .= '# Copyright (C) ' . date('Y') . ' WooCommerce Role Category Pricing' . "\n";
    $pot_content .= '# This file is distributed under the same license as the WooCommerce Role Category Pricing package.' . "\n";
    $pot_content .= 'msgid ""' . "\n";
    $pot_content .= 'msgstr ""' . "\n";
    $pot_content .= '"Project-Id-Version: WooCommerce Role Category Pricing 1.0.0\n"' . "\n";
    $pot_content .= '"Report-Msgid-Bugs-To: https://github.com/your-username/woocommerce-role-category-pricing/issues\n"' . "\n";
    $pot_content .= '"POT-Creation-Date: ' . date('Y-m-d H:i') . '+0000\n"' . "\n";
    $pot_content .= '"MIME-Version: 1.0\n"' . "\n";
    $pot_content .= '"Content-Type: text/plain; charset=UTF-8\n"' . "\n";
    $pot_content .= '"Content-Transfer-Encoding: 8bit\n"' . "\n";
    $pot_content .= '"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\n"' . "\n";
    $pot_content .= '"Last-Translator: FULL NAME <EMAIL@ADDRESS>\n"' . "\n";
    $pot_content .= '"Language-Team: LANGUAGE <LL@li.org>\n"' . "\n";
    $pot_content .= '"Language: \n"' . "\n";
    $pot_content .= '"Plural-Forms: nplurals=2; plural=(n != 1);\n"' . "\n";
    $pot_content .= '"X-Generator: WRCP POT Generator\n"' . "\n";
    $pot_content .= '"X-Domain: woocommerce-role-category-pricing\n"' . "\n\n";
    
    // Add strings
    foreach ($strings as $string => $files) {
        foreach ($files as $file) {
            $pot_content .= '#: ' . $file . "\n";
        }
        $pot_content .= 'msgid "' . addslashes($string) . '"' . "\n";
        $pot_content .= 'msgstr ""' . "\n\n";
    }
    
    return $pot_content;
}

// Only run if called directly (not included)
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $strings = wrcp_extract_translatable_strings();
    $pot_content = wrcp_generate_pot_content($strings);
    
    $pot_file = __DIR__ . '/woocommerce-role-category-pricing.pot';
    file_put_contents($pot_file, $pot_content);
    
    echo "POT file generated: " . $pot_file . "\n";
    echo "Found " . count($strings) . " translatable strings.\n";
}