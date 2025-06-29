#!/usr/bin/env node

/**
 * Theme Audit - Full Audit Runner
 * 
 * This script runs the complete theme audit process:
 * 1. Color audit (hard-coded colors)
 * 2. Theme usage audit (theme integration)
 * 3. Merges reports into unified summary
 */

const { execSync } = require('child_process');
const path = require('path');
const fs = require('fs');

// Configuration
const CONFIG = {
  scriptsDir: __dirname,
  reportsDir: path.join(__dirname, 'reports')
};

console.log('🚀 Starting comprehensive theme audit...\n');

try {
  // Ensure reports directory exists
  if (!fs.existsSync(CONFIG.reportsDir)) {
    fs.mkdirSync(CONFIG.reportsDir, { recursive: true });
  }

  console.log('1️⃣ Running color audit...');
  execSync('node color-scanner.cjs', {
    cwd: CONFIG.scriptsDir,
    stdio: 'inherit'
  });

  console.log('\n2️⃣ Running theme usage audit...');
  execSync('node theme-usage-scanner.cjs', {
    cwd: CONFIG.scriptsDir,
    stdio: 'inherit'
  });

  console.log('\n3️⃣ Merging reports...');
  execSync('node merge-reports.cjs', {
    cwd: CONFIG.scriptsDir,
    stdio: 'inherit'
  });

  console.log('\n🎉 Theme audit complete!');
  console.log('\n📋 Next steps:');
  console.log('   1. Review the unified markdown report for actionable insights');
  console.log('   2. Start with critical theme safety issues');
  console.log('   3. Gradually replace hard-coded colors with glass-theme tokens');
  console.log('   4. Implement ESLint rules to prevent future violations');

} catch (error) {
  console.error('\n❌ Audit failed:', error.message);
  process.exit(1);
}
