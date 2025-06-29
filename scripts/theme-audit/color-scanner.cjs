#!/usr/bin/env node

/**
 * Theme Audit - Color Anti-Pattern Scanner
 * 
 * This script scans all .tsx/.jsx files in the project and flags:
 * 1. Inline style rules with color/background/border properties using hex, rgb, or literal colors
 * 2. Tailwind classes (bg-*, text-*, border-*) that are NOT prefixed with 'glass-' or mapped in glass-theme.ts
 * 3. Hard-coded SVG fill/stroke attributes
 * 
 * Output: CSV/JSON report with file path, line number, and offending snippet
 */

const fs = require('fs');
const path = require('path');
const { glob } = require('glob');

// Configuration
const CONFIG = {
  projectRoot: path.resolve(__dirname, '../..'),
  outputDir: path.join(__dirname, 'reports'),
  scanPatterns: [
    'resources/js/**/*.tsx',
    'resources/js/**/*.jsx'
  ],
  excludePatterns: [
    '**/node_modules/**',
    '**/dist/**',
    '**/build/**'
  ]
};

// Color detection patterns
const COLOR_PATTERNS = {
  // Hex colors: #fff, #ffffff, #FFF, etc.
  hex: /#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})\b/g,
  
  // RGB/RGBA colors: rgb(255,255,255), rgba(255,255,255,0.5)
  rgb: /rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(?:,\s*[\d.]+)?\s*\)/g,
  
  // HSL/HSLA colors: hsl(0,0%,100%), hsla(0,0%,100%,0.5)
  hsl: /hsla?\(\s*\d+\s*,\s*\d+%\s*,\s*\d+%\s*(?:,\s*[\d.]+)?\s*\)/g,
  
  // Named colors (common ones that should be avoided)
  namedColors: /\b(white|black|red|green|blue|yellow|orange|purple|pink|gray|grey|brown|cyan|magenta|lime|navy|teal|olive|maroon|silver|gold)\b/gi
};

// Tailwind color class patterns (that should use glass- prefix)
const TAILWIND_COLOR_PATTERNS = {
  background: /\bbg-(?!glass-)(?!white\/|black\/|transparent|current|inherit)[\w-]+/g,
  text: /\btext-(?!glass-)(?!white\/|black\/|transparent|current|inherit)[\w-]+/g,
  border: /\bborder-(?!glass-)(?!white\/|black\/|transparent|current|inherit)[\w-]+/g,
  shadow: /\bshadow-(?!glass-)(?!none|sm|md|lg|xl|2xl|inner)[\w-]+/g
};

// CSS property patterns for inline styles
const INLINE_STYLE_PATTERNS = {
  color: /(?:^|[^-\w])(?:color|backgroundColor|borderColor|borderTopColor|borderRightColor|borderBottomColor|borderLeftColor|fill|stroke)\s*:\s*([^;,}]+)/gi,
  background: /(?:^|[^-\w])background(?:Color)?\s*:\s*([^;,}]+)/gi
};

// SVG attribute patterns
const SVG_PATTERNS = {
  fill: /\bfill\s*=\s*["']([^"']+)["']/g,
  stroke: /\bstroke\s*=\s*["']([^"']+)["']/g
};

class ColorScanner {
  constructor() {
    this.results = [];
    this.glassThemeColors = new Set();
    this.loadGlassThemeColors();
  }

  /**
   * Load existing glass theme colors from glass-theme.ts
   */
  loadGlassThemeColors() {
    const glassThemePath = path.join(CONFIG.projectRoot, 'resources/js/theme/glass-theme.ts');
    
    try {
      const content = fs.readFileSync(glassThemePath, 'utf8');
      
      // Extract Tailwind classes from the theme file
      const tailwindMatches = content.match(/['"]((?:bg-|text-|border-|shadow-)[\w-/\[\]\.]+)['"]/g);
      
      if (tailwindMatches) {
        tailwindMatches.forEach(match => {
          const className = match.slice(1, -1); // Remove quotes
          this.glassThemeColors.add(className);
        });
      }
      
      console.log(`✓ Loaded ${this.glassThemeColors.size} theme colors from glass-theme.ts`);
    } catch (error) {
      console.warn('⚠️  Could not load glass-theme.ts:', error.message);
    }
  }

  /**
   * Scan all files matching the patterns
   */
  async scanFiles() {
    console.log('🔍 Scanning for color anti-patterns...\n');

    // Create output directory
    if (!fs.existsSync(CONFIG.outputDir)) {
      fs.mkdirSync(CONFIG.outputDir, { recursive: true });
    }

    // Get all files to scan
    const files = [];
    for (const pattern of CONFIG.scanPatterns) {
      const matches = await glob(pattern, {
        cwd: CONFIG.projectRoot,
        ignore: CONFIG.excludePatterns,
        absolute: true
      });
      files.push(...matches);
    }

    console.log(`📁 Found ${files.length} files to scan`);

    // Scan each file
    for (const filePath of files) {
      await this.scanFile(filePath);
    }

    console.log(`\n📊 Scan complete: ${this.results.length} issues found`);
    
    // Generate reports
    this.generateReports();
  }

  /**
   * Scan a single file for color anti-patterns
   */
  async scanFile(filePath) {
    try {
      const content = fs.readFileSync(filePath, 'utf8');
      const relativePath = path.relative(CONFIG.projectRoot, filePath);
      const lines = content.split('\n');

      // Scan each line
      lines.forEach((line, index) => {
        const lineNumber = index + 1;
        
        // Skip comments
        if (line.trim().startsWith('//') || line.trim().startsWith('/*')) {
          return;
        }

        // Check for inline style color violations
        this.checkInlineStyles(relativePath, lineNumber, line);
        
        // Check for non-themed Tailwind classes
        this.checkTailwindClasses(relativePath, lineNumber, line);
        
        // Check for hard-coded SVG attributes
        this.checkSvgAttributes(relativePath, lineNumber, line);
      });

    } catch (error) {
      console.error(`❌ Error scanning ${filePath}:`, error.message);
    }
  }

  /**
   * Check for inline style color violations
   */
  checkInlineStyles(filePath, lineNumber, line) {
    // Check for style props with color values
    const styleMatches = line.matchAll(/style\s*=\s*\{?\{([^}]+)\}?\}?/g);
    
    for (const match of styleMatches) {
      const styleContent = match[1];
      
      // Check each color pattern
      Object.entries(COLOR_PATTERNS).forEach(([patternName, pattern]) => {
        pattern.lastIndex = 0; // Reset regex
        const colorMatches = styleContent.matchAll(pattern);
        
        for (const colorMatch of colorMatches) {
          this.addResult({
            filePath,
            lineNumber,
            type: 'inline-style',
            pattern: patternName,
            code: line.trim(),
            match: colorMatch[0],
            severity: 'critical',
            message: `Inline style uses ${patternName} color instead of glass theme`
          });
        }
      });
    }

    // Check for CSS property patterns
    Object.entries(INLINE_STYLE_PATTERNS).forEach(([property, pattern]) => {
      pattern.lastIndex = 0; // Reset regex
      const matches = line.matchAll(pattern);
      
      for (const match of matches) {
        const value = match[1];
        
        // Check if the value contains color patterns
        if (this.containsColorValue(value)) {
          this.addResult({
            filePath,
            lineNumber,
            type: 'css-property',
            pattern: property,
            code: line.trim(),
            match: match[0],
            severity: 'critical',
            message: `CSS ${property} property uses hard-coded color instead of glass theme`
          });
        }
      }
    });
  }

  /**
   * Check for non-themed Tailwind classes
   */
  checkTailwindClasses(filePath, lineNumber, line) {
    // Skip if line contains glass- prefix or theme references
    if (line.includes('glass-') || line.includes('themes[') || line.includes('t.')) {
      return;
    }

    Object.entries(TAILWIND_COLOR_PATTERNS).forEach(([category, pattern]) => {
      pattern.lastIndex = 0; // Reset regex
      const matches = line.matchAll(pattern);
      
      for (const match of matches) {
        const className = match[0];
        
        // Skip if this class is defined in glass theme
        if (this.glassThemeColors.has(className)) {
          return;
        }

        // Skip certain safe classes
        if (this.isSafeClass(className)) {
          return;
        }

        this.addResult({
          filePath,
          lineNumber,
          type: 'tailwind-class',
          pattern: category,
          code: line.trim(),
          match: className,
          severity: 'warning',
          message: `Tailwind ${category} class should use glass- prefix or be defined in glass-theme.ts`
        });
      }
    });
  }

  /**
   * Check for hard-coded SVG attributes
   */
  checkSvgAttributes(filePath, lineNumber, line) {
    Object.entries(SVG_PATTERNS).forEach(([attribute, pattern]) => {
      pattern.lastIndex = 0; // Reset regex
      const matches = line.matchAll(pattern);
      
      for (const match of matches) {
        const value = match[1];
        
        // Skip currentColor, inherit, none, and CSS custom properties
        if (['currentColor', 'inherit', 'none', 'transparent'].includes(value) || 
            value.startsWith('var(') || value.startsWith('url(')) {
          continue;
        }

        // Check if it's a hard-coded color
        if (this.containsColorValue(value)) {
          this.addResult({
            filePath,
            lineNumber,
            type: 'svg-attribute',
            pattern: attribute,
            code: line.trim(),
            match: match[0],
            severity: 'warning',
            message: `SVG ${attribute} attribute uses hard-coded color instead of theme value`
          });
        }
      }
    });
  }

  /**
   * Check if a value contains color patterns
   */
  containsColorValue(value) {
    return Object.values(COLOR_PATTERNS).some(pattern => {
      pattern.lastIndex = 0;
      return pattern.test(value);
    });
  }

  /**
   * Check if a Tailwind class is safe to use (exceptions)
   */
  isSafeClass(className) {
    const safePatterns = [
      /^bg-opacity-/,
      /^text-opacity-/,
      /^border-opacity-/,
      /^bg-gradient-/,
      /^text-\d+$/,  // Font weights: text-sm, text-lg, etc.
      /^shadow-(sm|md|lg|xl|2xl|inner|none)$/,
      /\/\[/,  // Arbitrary values like bg-white/[0.05]
      /-current$/,
      /-inherit$/,
      /-transparent$/
    ];

    return safePatterns.some(pattern => pattern.test(className));
  }

  /**
   * Add a result to the findings
   */
  addResult(result) {
    this.results.push({
      ...result,
      timestamp: new Date().toISOString()
    });
  }

  /**
   * Generate CSV and JSON reports
   */
  generateReports() {
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    
    // Generate JSON report
    const jsonReport = {
      metadata: {
        scanDate: new Date().toISOString(),
        totalIssues: this.results.length,
        severityBreakdown: this.getSeverityBreakdown(),
        typeBreakdown: this.getTypeBreakdown()
      },
      results: this.results
    };

    const jsonPath = path.join(CONFIG.outputDir, `color-audit-${timestamp}.json`);
    fs.writeFileSync(jsonPath, JSON.stringify(jsonReport, null, 2));

    // Generate CSV report
    const csvHeaders = ['File Path', 'Line Number', 'Type', 'Pattern', 'Severity', 'Match', 'Message', 'Code'];
    const csvRows = this.results.map(result => [
      result.filePath,
      result.lineNumber,
      result.type,
      result.pattern,
      result.severity,
      result.match,
      result.message,
      `"${result.code.replace(/"/g, '""')}"`
    ]);

    const csvContent = [csvHeaders, ...csvRows].map(row => row.join(',')).join('\n');
    const csvPath = path.join(CONFIG.outputDir, `color-audit-${timestamp}.csv`);
    fs.writeFileSync(csvPath, csvContent);

    console.log(`\n📄 Reports generated:`);
    console.log(`   JSON: ${jsonPath}`);
    console.log(`   CSV:  ${csvPath}`);

    // Print summary
    this.printSummary();
  }

  /**
   * Get severity breakdown
   */
  getSeverityBreakdown() {
    const breakdown = { critical: 0, warning: 0, info: 0 };
    this.results.forEach(result => {
      breakdown[result.severity] = (breakdown[result.severity] || 0) + 1;
    });
    return breakdown;
  }

  /**
   * Get type breakdown
   */
  getTypeBreakdown() {
    const breakdown = {};
    this.results.forEach(result => {
      breakdown[result.type] = (breakdown[result.type] || 0) + 1;
    });
    return breakdown;
  }

  /**
   * Print summary to console
   */
  printSummary() {
    const severityBreakdown = this.getSeverityBreakdown();
    const typeBreakdown = this.getTypeBreakdown();

    console.log(`\n📊 SUMMARY:`);
    console.log(`   Total Issues: ${this.results.length}`);
    console.log(`   🚨 Critical: ${severityBreakdown.critical || 0}`);
    console.log(`   ⚠️  Warning:  ${severityBreakdown.warning || 0}`);
    console.log(`   ℹ️  Info:     ${severityBreakdown.info || 0}`);
    
    console.log(`\n📋 BY TYPE:`);
    Object.entries(typeBreakdown).forEach(([type, count]) => {
      console.log(`   ${type}: ${count}`);
    });

    if (this.results.length > 0) {
      console.log(`\n🔥 TOP OFFENDERS:`);
      const fileBreakdown = {};
      this.results.forEach(result => {
        fileBreakdown[result.filePath] = (fileBreakdown[result.filePath] || 0) + 1;
      });

      Object.entries(fileBreakdown)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 5)
        .forEach(([file, count]) => {
          console.log(`   ${file}: ${count} issues`);
        });
    }
  }
}

// Execute if run directly
if (require.main === module) {
  const scanner = new ColorScanner();
  scanner.scanFiles().catch(console.error);
}

module.exports = ColorScanner;
