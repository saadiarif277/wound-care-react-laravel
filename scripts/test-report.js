#!/usr/bin/env node

import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';

const colors = {
  reset: '\x1b[0m',
  bright: '\x1b[1m',
  green: '\x1b[32m',
  red: '\x1b[31m',
  yellow: '\x1b[33m',
  blue: '\x1b[34m',
};

console.log(`${colors.bright}${colors.blue}Running Comprehensive Test Suite${colors.reset}\n`);

// Run PHP tests with coverage
console.log(`${colors.yellow}Running PHP tests...${colors.reset}`);
try {
  execSync('php artisan test --coverage-text', { stdio: 'inherit' });
} catch (error) {
  console.error(`${colors.red}PHP tests failed${colors.reset}`);
  process.exit(1);
}

// Run JavaScript tests with coverage
console.log(`\n${colors.yellow}Running JavaScript tests...${colors.reset}`);
try {
  execSync('npm run test:coverage', { stdio: 'inherit' });
} catch (error) {
  console.error(`${colors.red}JavaScript tests failed${colors.reset}`);
  process.exit(1);
}

// Generate coverage summary
console.log(`\n${colors.yellow}Generating coverage summary...${colors.reset}`);

// Read PHP coverage
let phpCoverage = { lines: 0, statements: 0, functions: 0, branches: 0 };
if (fs.existsSync('tests/coverage/coverage.txt')) {
  const phpCoverageText = fs.readFileSync('tests/coverage/coverage.txt', 'utf8');
  const match = phpCoverageText.match(/Lines:\s+(\d+\.\d+)%/);
  if (match) {
    phpCoverage.lines = parseFloat(match[1]);
  }
}

// Read JS coverage
let jsCoverage = { lines: 0, statements: 0, functions: 0, branches: 0 };
if (fs.existsSync('coverage/coverage-summary.json')) {
  const jsCoverageSummary = JSON.parse(fs.readFileSync('coverage/coverage-summary.json', 'utf8'));
  const total = jsCoverageSummary.total;
  jsCoverage = {
    lines: total.lines.pct,
    statements: total.statements.pct,
    functions: total.functions.pct,
    branches: total.branches.pct,
  };
}

// Calculate overall coverage
const overallCoverage = {
  lines: (phpCoverage.lines + jsCoverage.lines) / 2,
  statements: (phpCoverage.statements + jsCoverage.statements) / 2 || jsCoverage.statements,
  functions: (phpCoverage.functions + jsCoverage.functions) / 2 || jsCoverage.functions,
  branches: (phpCoverage.branches + jsCoverage.branches) / 2 || jsCoverage.branches,
};

// Display summary
console.log(`\n${colors.bright}Coverage Summary${colors.reset}`);
console.log('═'.repeat(50));
console.log(`PHP Coverage:`);
console.log(`  Lines: ${formatPercentage(phpCoverage.lines)}`);
console.log(`\nJavaScript Coverage:`);
console.log(`  Lines: ${formatPercentage(jsCoverage.lines)}`);
console.log(`  Statements: ${formatPercentage(jsCoverage.statements)}`);
console.log(`  Functions: ${formatPercentage(jsCoverage.functions)}`);
console.log(`  Branches: ${formatPercentage(jsCoverage.branches)}`);
console.log(`\n${colors.bright}Overall Coverage:${colors.reset}`);
console.log(`  Lines: ${formatPercentage(overallCoverage.lines)}`);
console.log('═'.repeat(50));

// Check if coverage meets thresholds
const threshold = 80;
const meetsThreshold = overallCoverage.lines >= threshold;

if (meetsThreshold) {
  console.log(`\n${colors.green}✓ Coverage meets the ${threshold}% threshold!${colors.reset}`);
} else {
  console.log(`\n${colors.red}✗ Coverage (${overallCoverage.lines.toFixed(2)}%) is below the ${threshold}% threshold${colors.reset}`);
  process.exit(1);
}

// Generate HTML report
console.log(`\n${colors.yellow}Generating HTML reports...${colors.reset}`);
console.log(`PHP coverage report: file://${path.resolve('tests/coverage/html/index.html')}`);
console.log(`JS coverage report: file://${path.resolve('coverage/lcov-report/index.html')}`);

function formatPercentage(value) {
  const color = value >= 80 ? colors.green : value >= 60 ? colors.yellow : colors.red;
  return `${color}${value.toFixed(2)}%${colors.reset}`;
}