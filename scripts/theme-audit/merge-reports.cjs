#!/usr/bin/env node

/**
 * Theme Audit - Report Merger
 * 
 * This script merges color audit and theme usage audit reports into a unified report
 * with prioritized issues and actionable recommendations.
 */

const fs = require('fs');
const path = require('path');
const { glob } = require('glob');

// Configuration
const CONFIG = {
  projectRoot: path.resolve(__dirname, '../..'),
  reportsDir: path.join(__dirname, 'reports'),
  outputDir: path.join(__dirname, 'reports')
};

class ReportMerger {
  constructor() {
    this.colorAuditResults = [];
    this.themeUsageResults = [];
    this.mergedResults = [];
  }

  /**
   * Load and merge all reports
   */
  async mergeReports() {
    console.log('🔄 Merging theme audit reports...\n');

    // Find latest reports
    const colorReport = await this.findLatestReport('color-audit-*.json');
    const themeUsageReport = await this.findLatestReport('theme-usage-audit-*.json');

    if (!colorReport || !themeUsageReport) {
      console.error('❌ Could not find both audit reports. Please run the audit scripts first.');
      return;
    }

    console.log(`📊 Loading color audit: ${path.basename(colorReport)}`);
    console.log(`📊 Loading theme usage audit: ${path.basename(themeUsageReport)}`);

    // Load reports
    const colorData = JSON.parse(fs.readFileSync(colorReport, 'utf8'));
    const themeData = JSON.parse(fs.readFileSync(themeUsageReport, 'utf8'));

    this.colorAuditResults = colorData.results || [];
    this.themeUsageResults = themeData.results || [];

    // Merge and prioritize
    this.mergeAndPrioritize();

    // Generate unified report
    this.generateUnifiedReport(colorData.metadata, themeData.metadata);

    console.log(`\n✅ Merged report generated with ${this.mergedResults.length} prioritized issues`);
  }

  /**
   * Find the latest report file matching pattern
   */
  async findLatestReport(pattern) {
    const files = await glob(pattern, {
      cwd: CONFIG.reportsDir,
      absolute: true
    });

    if (files.length === 0) {
      return null;
    }

    // Sort by modification time, newest first
    files.sort((a, b) => {
      const statA = fs.statSync(a);
      const statB = fs.statSync(b);
      return statB.mtime.getTime() - statA.mtime.getTime();
    });

    return files[0];
  }

  /**
   * Merge and prioritize issues
   */
  mergeAndPrioritize() {
    // Group by file for better organization
    const fileGroups = new Map();

    // Process color audit results
    this.colorAuditResults.forEach(issue => {
      const normalizedPath = this.normalizePath(issue.filePath);
      if (!fileGroups.has(normalizedPath)) {
        fileGroups.set(normalizedPath, {
          filePath: normalizedPath,
          colorIssues: [],
          themeIssues: [],
          totalIssues: 0,
          criticalIssues: 0,
          warningIssues: 0
        });
      }

      const fileGroup = fileGroups.get(normalizedPath);
      fileGroup.colorIssues.push(issue);
      fileGroup.totalIssues++;
      
      if (issue.severity === 'critical') {
        fileGroup.criticalIssues++;
      } else if (issue.severity === 'warning') {
        fileGroup.warningIssues++;
      }
    });

    // Process theme usage results
    this.themeUsageResults.forEach(issue => {
      const normalizedPath = this.normalizePath(issue.filePath);
      if (!fileGroups.has(normalizedPath)) {
        fileGroups.set(normalizedPath, {
          filePath: normalizedPath,
          colorIssues: [],
          themeIssues: [],
          totalIssues: 0,
          criticalIssues: 0,
          warningIssues: 0
        });
      }

      const fileGroup = fileGroups.get(normalizedPath);
      fileGroup.themeIssues.push(issue);
      fileGroup.totalIssues++;
      
      if (issue.severity === 'critical') {
        fileGroup.criticalIssues++;
      } else if (issue.severity === 'warning') {
        fileGroup.warningIssues++;
      }
    });

    // Convert to array and sort by priority
    this.mergedResults = Array.from(fileGroups.values()).sort((a, b) => {
      // Sort by critical issues first, then by total issues
      if (a.criticalIssues !== b.criticalIssues) {
        return b.criticalIssues - a.criticalIssues;
      }
      return b.totalIssues - a.totalIssues;
    });
  }

  /**
   * Normalize file path for consistent grouping
   */
  normalizePath(filePath) {
    return filePath.replace(/\\/g, '/');
  }

  /**
   * Generate unified report
   */
  generateUnifiedReport(colorMetadata, themeMetadata) {
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    
    // Calculate merged statistics
    const totalColorIssues = colorMetadata.totalIssues || 0;
    const totalThemeIssues = themeMetadata.totalIssues || 0;
    const totalIssues = totalColorIssues + totalThemeIssues;

    const mergedSeverityBreakdown = {
      critical: (colorMetadata.severityBreakdown?.critical || 0) + (themeMetadata.severityBreakdown?.critical || 0),
      warning: (colorMetadata.severityBreakdown?.warning || 0) + (themeMetadata.severityBreakdown?.warning || 0),
      info: (colorMetadata.severityBreakdown?.info || 0) + (themeMetadata.severityBreakdown?.info || 0)
    };

    const mergedTypeBreakdown = {
      ...colorMetadata.typeBreakdown,
      ...themeMetadata.typeBreakdown
    };

    // Generate action items
    const actionItems = this.generateActionItems();

    const unifiedReport = {
      metadata: {
        reportType: 'unified-theme-audit',
        scanDate: new Date().toISOString(),
        colorAuditDate: colorMetadata.scanDate,
        themeUsageAuditDate: themeMetadata.scanDate,
        totalIssues,
        totalColorIssues,
        totalThemeIssues,
        severityBreakdown: mergedSeverityBreakdown,
        typeBreakdown: mergedTypeBreakdown,
        filesWithIssues: this.mergedResults.length,
        componentsAnalyzed: themeMetadata.componentsAnalyzed || 0
      },
      prioritizedFiles: this.mergedResults.slice(0, 20), // Top 20 files with most issues
      actionItems,
      summary: this.generateSummary(mergedSeverityBreakdown, mergedTypeBreakdown)
    };

    // Write JSON report
    const jsonPath = path.join(CONFIG.outputDir, `unified-theme-audit-${timestamp}.json`);
    fs.writeFileSync(jsonPath, JSON.stringify(unifiedReport, null, 2));

    // Generate markdown summary
    this.generateMarkdownSummary(unifiedReport, timestamp);

    console.log(`\n📄 Unified reports generated:`);
    console.log(`   JSON: ${jsonPath}`);
    console.log(`   Markdown: ${path.join(CONFIG.outputDir, `unified-theme-audit-${timestamp}.md`)}`);

    // Print console summary
    this.printConsoleSummary(unifiedReport);
  }

  /**
   * Generate action items based on issue types
   */
  generateActionItems() {
    const actions = [];

    // Critical issues first
    const criticalFiles = this.mergedResults.filter(file => file.criticalIssues > 0);
    if (criticalFiles.length > 0) {
      actions.push({
        priority: 'critical',
        category: 'Theme Safety',
        title: 'Fix unsafe theme access patterns',
        description: `${criticalFiles.length} files have critical theme safety issues`,
        files: criticalFiles.slice(0, 10).map(f => f.filePath),
        action: 'Replace unsafe theme destructuring with safe patterns using fallbacks'
      });
    }

    // Color standardization
    const colorFiles = this.mergedResults.filter(file => file.colorIssues.length > 0);
    if (colorFiles.length > 0) {
      actions.push({
        priority: 'high',
        category: 'Color Standardization',
        title: 'Replace hard-coded colors with glass-theme tokens',
        description: `${colorFiles.length} files use hard-coded colors instead of theme tokens`,
        files: colorFiles.slice(0, 10).map(f => f.filePath),
        action: 'Replace Tailwind color classes with glass- prefixed theme tokens'
      });
    }

    // Missing theme imports
    const missingImportFiles = this.mergedResults.filter(file => 
      file.themeIssues.some(issue => issue.type === 'missing-theme-import')
    );
    if (missingImportFiles.length > 0) {
      actions.push({
        priority: 'medium',
        category: 'Theme Integration',
        title: 'Add missing theme imports',
        description: `${missingImportFiles.length} components use theme patterns without importing useTheme`,
        files: missingImportFiles.slice(0, 10).map(f => f.filePath),
        action: 'Add useTheme import and implement proper theme context usage'
      });
    }

    // MainLayout usage
    const missingLayoutFiles = this.mergedResults.filter(file => 
      file.themeIssues.some(issue => issue.type === 'missing-main-layout')
    );
    if (missingLayoutFiles.length > 0) {
      actions.push({
        priority: 'medium',
        category: 'Layout Consistency',
        title: 'Wrap pages in MainLayout',
        description: `${missingLayoutFiles.length} pages are not using MainLayout`,
        files: missingLayoutFiles.slice(0, 10).map(f => f.filePath),
        action: 'Import and wrap page content with MainLayout component'
      });
    }

    return actions;
  }

  /**
   * Generate text summary
   */
  generateSummary(severityBreakdown, typeBreakdown) {
    const total = severityBreakdown.critical + severityBreakdown.warning + severityBreakdown.info;
    
    return {
      overview: `Found ${total} total theme-related issues across ${this.mergedResults.length} files`,
      severity: `${severityBreakdown.critical} critical, ${severityBreakdown.warning} warning, ${severityBreakdown.info} info`,
      topIssueTypes: Object.entries(typeBreakdown)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 5)
        .map(([type, count]) => `${type}: ${count}`)
        .join(', '),
      recommendation: severityBreakdown.critical > 0 
        ? 'Address critical theme safety issues immediately'
        : 'Focus on standardizing color usage and theme integration'
    };
  }

  /**
   * Generate markdown summary
   */
  generateMarkdownSummary(report, timestamp) {
    const mdPath = path.join(CONFIG.outputDir, `unified-theme-audit-${timestamp}.md`);
    
    const markdown = `# Theme Audit Summary

*Generated on ${new Date().toLocaleString()}*

## Overview

${report.summary.overview}

**Severity Breakdown:** ${report.summary.severity}

**Recommendation:** ${report.summary.recommendation}

## Critical Action Items

${report.actionItems.filter(item => item.priority === 'critical').map(item => `
### ${item.title}
- **Category:** ${item.category}
- **Priority:** ${item.priority.toUpperCase()}
- **Description:** ${item.description}
- **Action:** ${item.action}

**Affected Files:**
${item.files.map(file => `- \`${file}\``).join('\n')}
`).join('\n')}

## High Priority Issues

${report.actionItems.filter(item => item.priority === 'high').map(item => `
### ${item.title}
- **Category:** ${item.category}
- **Description:** ${item.description}
- **Action:** ${item.action}

**Top Affected Files:**
${item.files.slice(0, 5).map(file => `- \`${file}\``).join('\n')}
`).join('\n')}

## Top Files Requiring Attention

${report.prioritizedFiles.slice(0, 10).map((file, index) => `
${index + 1}. **\`${file.filePath}\`**
   - Total Issues: ${file.totalIssues}
   - Critical: ${file.criticalIssues}
   - Warnings: ${file.warningIssues}
   - Color Issues: ${file.colorIssues.length}
   - Theme Issues: ${file.themeIssues.length}
`).join('\n')}

## Issue Type Breakdown

${Object.entries(report.metadata.typeBreakdown)
  .sort((a, b) => b[1] - a[1])
  .map(([type, count]) => `- **${type}**: ${count} issues`)
  .join('\n')}

## Next Steps

1. **Address Critical Issues**: Start with files having critical theme safety issues
2. **Standardize Colors**: Replace hard-coded colors with glass-theme tokens
3. **Improve Theme Integration**: Add missing theme imports and context usage
4. **Ensure Layout Consistency**: Wrap pages in MainLayout
5. **Set Up Linting**: Implement ESLint rules to prevent future violations

## Implementation Guide

### Safe Theme Usage Pattern
\`\`\`typescript
// ❌ Unsafe
const { theme } = useTheme();

// ✅ Safe
const { theme = 'dark' } = useTheme() ?? {};
\`\`\`

### Glass Theme Integration
\`\`\`typescript
// ❌ Hard-coded
<div className="bg-blue-500 text-white">

// ✅ Theme-based
<div className={cn(t.card.background, t.text.primary)}>
\`\`\`

### MainLayout Usage
\`\`\`typescript
// ❌ Direct page export
export default function MyPage() {
  return <div>Content</div>;
}

// ✅ Wrapped in MainLayout
export default function MyPage() {
  return (
    <MainLayout>
      <div>Content</div>
    </MainLayout>
  );
}
\`\`\`
`;

    fs.writeFileSync(mdPath, markdown);
  }

  /**
   * Print console summary
   */
  printConsoleSummary(report) {
    console.log(`\n📊 UNIFIED THEME AUDIT SUMMARY:`);
    console.log(`   ${report.summary.overview}`);
    console.log(`   Severity: ${report.summary.severity}`);
    console.log(`   Files Affected: ${report.metadata.filesWithIssues}`);
    
    console.log(`\n🚨 CRITICAL ACTION ITEMS:`);
    report.actionItems
      .filter(item => item.priority === 'critical')
      .forEach(item => {
        console.log(`   • ${item.title} (${item.files.length} files)`);
      });
    
    console.log(`\n⚠️  HIGH PRIORITY ITEMS:`);
    report.actionItems
      .filter(item => item.priority === 'high')
      .forEach(item => {
        console.log(`   • ${item.title} (${item.files.length} files)`);
      });

    console.log(`\n🔥 TOP FILES TO FIX:`);
    report.prioritizedFiles.slice(0, 5).forEach((file, index) => {
      console.log(`   ${index + 1}. ${file.filePath}: ${file.totalIssues} issues (${file.criticalIssues} critical)`);
    });

    console.log(`\n💡 RECOMMENDATION: ${report.summary.recommendation}`);
  }
}

// Execute if run directly
if (require.main === module) {
  const merger = new ReportMerger();
  merger.mergeReports().catch(console.error);
}

module.exports = ReportMerger;
