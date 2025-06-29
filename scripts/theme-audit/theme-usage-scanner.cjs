#!/usr/bin/env node

/**
 * Theme Audit - Theme Usage Scanner
 * 
 * This script analyzes React components for proper theme integration:
 * 1. Flags components using theme-related patterns without importing useTheme
 * 2. Identifies pages not wrapped with MainLayout
 * 3. Checks for unsafe theme access patterns
 * 
 * Output: JSON report with missing theme integration issues
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
  pagePatterns: [
    'resources/js/Pages/**/*.tsx',
    'resources/js/Pages/**/*.jsx'
  ],
  excludePatterns: [
    '**/node_modules/**',
    '**/dist/**',
    '**/build/**',
    '**/contexts/ThemeContext.tsx', // Skip the theme context itself
    '**/theme/glass-theme.ts'       // Skip theme definitions
  ]
};

// Patterns to detect theme usage
const THEME_USAGE_PATTERNS = {
  // Direct theme references
  themeProps: /\btheme\s*[:=]/g,
  darkModeClasses: /\bdark:/g,
  lightModeClasses: /\blight:/g,
  
  // Glass theme references
  glassThemeImport: /from\s+['"]@\/theme\/glass-theme['"];?/g,
  themesUsage: /\bthemes\[/g,
  glassThemeUsage: /\bt\./g,
  
  // Conditional theme styling
  conditionalTheme: /theme\s*===\s*['"](?:dark|light)['"]/g,
  
  // Theme-aware class interpolation
  classNameInterpolation: /className.*\$\{.*(?:theme|dark|light)/g
};

// Import detection patterns
const IMPORT_PATTERNS = {
  useTheme: /import\s*{[^}]*useTheme[^}]*}\s*from\s*['"]@\/contexts\/ThemeContext['"];?/g,
  ThemeProvider: /import\s*{[^}]*ThemeProvider[^}]*}\s*from\s*['"]@\/contexts\/ThemeContext['"];?/g,
  MainLayout: /import\s*.*MainLayout.*\s*from\s*['"]@\/Layouts\/MainLayout['"];?/g,
  glassTheme: /import\s*{[^}]*(?:themes|cn|glassTheme)[^}]*}\s*from\s*['"]@\/theme\/glass-theme['"];?/g
};

// Layout usage patterns
const LAYOUT_PATTERNS = {
  mainLayoutWrapper: /<MainLayout[^>]*>/g,
  layoutExport: /export\s+default\s+function\s+\w+.*MainLayout/g
};

class ThemeUsageScanner {
  constructor() {
    this.results = [];
    this.componentAnalysis = new Map();
  }

  /**
   * Scan all files for theme usage issues
   */
  async scanFiles() {
    console.log('🔍 Scanning for theme usage patterns...\n');

    // Create output directory
    if (!fs.existsSync(CONFIG.outputDir)) {
      fs.mkdirSync(CONFIG.outputDir, { recursive: true });
    }

    // Get all files to scan
    const allFiles = [];
    for (const pattern of CONFIG.scanPatterns) {
      const matches = await glob(pattern, {
        cwd: CONFIG.projectRoot,
        ignore: CONFIG.excludePatterns,
        absolute: true
      });
      allFiles.push(...matches);
    }

    // Get page files specifically
    const pageFiles = [];
    for (const pattern of CONFIG.pagePatterns) {
      const matches = await glob(pattern, {
        cwd: CONFIG.projectRoot,
        ignore: CONFIG.excludePatterns,
        absolute: true
      });
      pageFiles.push(...matches);
    }

    console.log(`📁 Found ${allFiles.length} component files to scan`);
    console.log(`📄 Found ${pageFiles.length} page files to check for MainLayout`);

    // Scan components for theme usage
    for (const filePath of allFiles) {
      await this.analyzeComponent(filePath);
    }

    // Check pages for MainLayout usage
    for (const filePath of pageFiles) {
      await this.checkPageLayout(filePath);
    }

    console.log(`\n📊 Analysis complete: ${this.results.length} issues found`);
    
    // Generate reports
    this.generateReports();
  }

  /**
   * Analyze a component for theme usage patterns
   */
  async analyzeComponent(filePath) {
    try {
      const content = fs.readFileSync(filePath, 'utf8');
      const relativePath = path.relative(CONFIG.projectRoot, filePath);
      
      // Check imports
      const imports = this.analyzeImports(content);
      
      // Check theme usage patterns
      const themeUsage = this.analyzeThemeUsage(content);
      
      // Store component analysis
      this.componentAnalysis.set(relativePath, {
        imports,
        themeUsage,
        content
      });

      // Check for issues
      this.checkThemeUsageIssues(relativePath, imports, themeUsage, content);

    } catch (error) {
      console.error(`❌ Error analyzing ${filePath}:`, error.message);
    }
  }

  /**
   * Analyze imports in a file
   */
  analyzeImports(content) {
    const imports = {
      useTheme: false,
      ThemeProvider: false,
      MainLayout: false,
      glassTheme: false
    };

    Object.entries(IMPORT_PATTERNS).forEach(([importName, pattern]) => {
      if (pattern.test(content)) {
        imports[importName] = true;
      }
    });

    return imports;
  }

  /**
   * Analyze theme usage patterns in content
   */
  analyzeThemeUsage(content) {
    const usage = {
      themeProps: [],
      darkModeClasses: [],
      lightModeClasses: [],
      glassThemeImport: [],
      glassThemeUsage: [],
      themesUsage: [],
      conditionalTheme: [],
      classNameInterpolation: []
    };

    const lines = content.split('\n');

    lines.forEach((line, index) => {
      const lineNumber = index + 1;
      
      Object.entries(THEME_USAGE_PATTERNS).forEach(([patternName, pattern]) => {
        pattern.lastIndex = 0; // Reset regex
        const matches = [...line.matchAll(pattern)];
        
        matches.forEach(match => {
          if (usage[patternName]) {
            usage[patternName].push({
              lineNumber,
              match: match[0],
              line: line.trim()
            });
          }
        });
      });
    });

    return usage;
  }

  /**
   * Check for theme usage issues
   */
  checkThemeUsageIssues(filePath, imports, themeUsage, content) {
    const hasThemeUsage = this.hasAnyThemeUsage(themeUsage);
    const hasUnsafeThemeAccess = this.hasUnsafeThemeAccess(content);

    // Issue 1: Uses theme patterns but doesn't import useTheme
    if (hasThemeUsage && !imports.useTheme && !imports.ThemeProvider) {
      this.addResult({
        filePath,
        type: 'missing-theme-import',
        severity: 'warning',
        message: 'Component uses theme-related patterns but does not import useTheme',
        details: {
          themeUsageFound: this.summarizeThemeUsage(themeUsage),
          suggestion: 'Add: import { useTheme } from "@/contexts/ThemeContext";'
        }
      });
    }

    // Issue 2: Uses glass theme utilities but doesn't import them
    if ((themeUsage.glassThemeUsage.length > 0 || themeUsage.themesUsage.length > 0) && !imports.glassTheme) {
      this.addResult({
        filePath,
        type: 'missing-glass-theme-import',
        severity: 'warning',
        message: 'Component uses glass theme utilities but does not import them',
        details: {
          glassThemeUsage: themeUsage.glassThemeUsage.length,
          themesUsage: themeUsage.themesUsage.length,
          suggestion: 'Add: import { themes, cn } from "@/theme/glass-theme";'
        }
      });
    }

    // Issue 3: Unsafe theme access (direct destructuring without fallback)
    if (hasUnsafeThemeAccess) {
      this.addResult({
        filePath,
        type: 'unsafe-theme-access',
        severity: 'critical',
        message: 'Component has unsafe theme access patterns',
        details: {
          unsafePatterns: this.findUnsafePatterns(content),
          suggestion: 'Use safe destructuring: const { theme = "dark" } = useTheme() ?? {};'
        }
      });
    }

    // Issue 4: Uses dark: or light: classes without proper theme handling
    if ((themeUsage.darkModeClasses.length > 0 || themeUsage.lightModeClasses.length > 0) && 
        !imports.useTheme && !imports.ThemeProvider) {
      this.addResult({
        filePath,
        type: 'responsive-theme-without-context',
        severity: 'warning',
        message: 'Component uses dark:/light: classes but may not have proper theme context',
        details: {
          darkModeClasses: themeUsage.darkModeClasses.length,
          lightModeClasses: themeUsage.lightModeClasses.length,
          suggestion: 'Ensure component is wrapped in ThemeProvider or import useTheme'
        }
      });
    }
  }

  /**
   * Check if page uses MainLayout
   */
  async checkPageLayout(filePath) {
    try {
      const content = fs.readFileSync(filePath, 'utf8');
      const relativePath = path.relative(CONFIG.projectRoot, filePath);
      
      const imports = this.analyzeImports(content);
      const hasMainLayoutWrapper = LAYOUT_PATTERNS.mainLayoutWrapper.test(content);
      const hasLayoutExport = LAYOUT_PATTERNS.layoutExport.test(content);

      // Check if it's a page component (has default export function)
      const hasDefaultExport = /export\s+default\s+function/.test(content);
      
      if (hasDefaultExport && !imports.MainLayout && !hasMainLayoutWrapper && !hasLayoutExport) {
        this.addResult({
          filePath: relativePath,
          type: 'missing-main-layout',
          severity: 'warning',
          message: 'Page component does not import or use MainLayout',
          details: {
            suggestion: 'Wrap page content with MainLayout or import MainLayout',
            isPageFile: true
          }
        });
      }

    } catch (error) {
      console.error(`❌ Error checking page layout ${filePath}:`, error.message);
    }
  }

  /**
   * Check if content has any theme usage
   */
  hasAnyThemeUsage(themeUsage) {
    return Object.values(themeUsage).some(usageArray => usageArray.length > 0);
  }

  /**
   * Check for unsafe theme access patterns
   */
  hasUnsafeThemeAccess(content) {
    // Look for direct destructuring without fallback
    const unsafePatterns = [
      /const\s*{\s*theme\s*}\s*=\s*useTheme\(\)\s*;/g,
      /const\s*{\s*theme\s*}\s*=\s*useTheme\(\)\s*$/gm,
      /\.theme\b(?!\s*\?\.|\.)/g // Access .theme without null safety
    ];

    return unsafePatterns.some(pattern => {
      pattern.lastIndex = 0;
      return pattern.test(content);
    });
  }

  /**
   * Find specific unsafe patterns in content
   */
  findUnsafePatterns(content) {
    const patterns = [];
    const lines = content.split('\n');

    lines.forEach((line, index) => {
      const lineNumber = index + 1;
      
      // Check for unsafe destructuring
      if (/const\s*{\s*theme\s*}\s*=\s*useTheme\(\)/.test(line)) {
        patterns.push({
          lineNumber,
          pattern: 'unsafe-destructuring',
          line: line.trim()
        });
      }

      // Check for direct property access without null safety
      if (/\.theme\b(?!\s*\?\.)/.test(line) && !line.includes('themes[')) {
        patterns.push({
          lineNumber,
          pattern: 'unsafe-property-access',
          line: line.trim()
        });
      }
    });

    return patterns;
  }

  /**
   * Summarize theme usage for reporting
   */
  summarizeThemeUsage(themeUsage) {
    const summary = {};
    Object.entries(themeUsage).forEach(([key, usageArray]) => {
      if (usageArray.length > 0) {
        summary[key] = usageArray.length;
      }
    });
    return summary;
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
   * Generate JSON report
   */
  generateReports() {
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    
    // Generate JSON report
    const jsonReport = {
      metadata: {
        scanDate: new Date().toISOString(),
        totalIssues: this.results.length,
        severityBreakdown: this.getSeverityBreakdown(),
        typeBreakdown: this.getTypeBreakdown(),
        componentsAnalyzed: this.componentAnalysis.size
      },
      results: this.results,
      componentSummary: this.generateComponentSummary()
    };

    const jsonPath = path.join(CONFIG.outputDir, `theme-usage-audit-${timestamp}.json`);
    fs.writeFileSync(jsonPath, JSON.stringify(jsonReport, null, 2));

    // Generate CSV report
    const csvHeaders = ['File Path', 'Type', 'Severity', 'Message', 'Suggestion'];
    const csvRows = this.results.map(result => [
      result.filePath,
      result.type,
      result.severity,
      result.message,
      result.details?.suggestion || ''
    ]);

    const csvContent = [csvHeaders, ...csvRows].map(row => 
      row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',')
    ).join('\n');
    
    const csvPath = path.join(CONFIG.outputDir, `theme-usage-audit-${timestamp}.csv`);
    fs.writeFileSync(csvPath, csvContent);

    console.log(`\n📄 Reports generated:`);
    console.log(`   JSON: ${jsonPath}`);
    console.log(`   CSV:  ${csvPath}`);

    // Print summary
    this.printSummary();
  }

  /**
   * Generate component summary
   */
  generateComponentSummary() {
    const summary = {
      totalComponents: this.componentAnalysis.size,
      componentsWithThemeImports: 0,
      componentsWithGlassThemeImports: 0,
      componentsWithThemeUsage: 0
    };

    for (const [filePath, analysis] of this.componentAnalysis) {
      if (analysis.imports.useTheme || analysis.imports.ThemeProvider) {
        summary.componentsWithThemeImports++;
      }
      if (analysis.imports.glassTheme) {
        summary.componentsWithGlassThemeImports++;
      }
      if (this.hasAnyThemeUsage(analysis.themeUsage)) {
        summary.componentsWithThemeUsage++;
      }
    }

    return summary;
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
    const componentSummary = this.generateComponentSummary();

    console.log(`\n📊 THEME USAGE SUMMARY:`);
    console.log(`   Total Issues: ${this.results.length}`);
    console.log(`   🚨 Critical: ${severityBreakdown.critical || 0}`);
    console.log(`   ⚠️  Warning:  ${severityBreakdown.warning || 0}`);
    console.log(`   ℹ️  Info:     ${severityBreakdown.info || 0}`);
    
    console.log(`\n📋 BY TYPE:`);
    Object.entries(typeBreakdown).forEach(([type, count]) => {
      console.log(`   ${type}: ${count}`);
    });

    console.log(`\n🧩 COMPONENT ANALYSIS:`);
    console.log(`   Total Components: ${componentSummary.totalComponents}`);
    console.log(`   With Theme Imports: ${componentSummary.componentsWithThemeImports}`);
    console.log(`   With Glass Theme Imports: ${componentSummary.componentsWithGlassThemeImports}`);
    console.log(`   With Theme Usage: ${componentSummary.componentsWithThemeUsage}`);

    if (this.results.length > 0) {
      console.log(`\n🔥 TOP ISSUES:`);
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
  const scanner = new ThemeUsageScanner();
  scanner.scanFiles().catch(console.error);
}

module.exports = ThemeUsageScanner;
