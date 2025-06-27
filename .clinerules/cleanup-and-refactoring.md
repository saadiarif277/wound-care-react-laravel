## Brief overview
Guidelines for systematic cleanup and refactoring practices, emphasizing automation, service consolidation, and elimination of redundant code. These rules are based on comprehensive DocuSeal template automation work and service cleanup patterns.

## Cleanup methodology
- Always clean up after completing a feature or refactoring task
- When finding duplicates, identify the most advanced version and either merge or ask user to delete the inferior version
- Remove hardcoded configurations in favor of database-driven or automated approaches
- Delete redundant services that have overlapping functionality
- Search for and eliminate unused files, configurations, and references after removing services

## Service consolidation approach
- Identify overlapping services performing similar functions
- Consolidate multiple services into a single, well-structured service when possible
- Remove services that duplicate functionality found in newer implementations
- Maintain backward compatibility when consolidating services
- Document what services were removed and why in completion summaries

## Automation over manual processes
- Replace manual configuration with automated discovery and sync systems
- Use database-driven configurations instead of hardcoded arrays
- Implement queue-based processing for scalable operations
- Create comprehensive testing commands for new automated systems
- Prefer API-driven data fetching over static configuration files

## Code organization standards
- Remove 300-400 line file limits when building comprehensive automation systems
- Create detailed progress tracking and logging for long-running operations
- Use descriptive command signatures with helpful options (--force, --queue, --manufacturer)
- Implement both immediate and background processing options
- Include comprehensive error handling and user-friendly output

## Documentation and completion
- Create detailed completion summaries documenting what was accomplished
- List specific files that were removed and why
- Explain the benefits achieved through refactoring
- Provide usage examples for new automated systems
- Include performance improvements and architectural benefits in summaries

## Development workflow
- Test new automated systems before removing old manual processes
- Use sequential thinking for complex refactoring tasks
- Create test commands to validate new functionality
- Search for references to removed files and clean them up
- Maintain HIPAA compliance and existing security patterns during refactoring
