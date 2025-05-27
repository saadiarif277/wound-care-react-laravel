# MSC Wound Care Portal Documentation

This directory contains comprehensive documentation for the MSC Wound Care Portal, organized by category for easy navigation and maintenance.

## 📁 Directory Structure

### 🏗️ `/architecture`
*System architecture and design documents*

**Coming Soon**: Architecture diagrams, system design documents, and technical specifications.

### 🔌 `/api`
*API documentation and integration guides*

- **`FHIR_Implementation_Guide.md`** - Complete FHIR R4 server implementation
- **`MSC-MVP FHIR Server REST API.md`** - OpenAPI specification for FHIR endpoints
- **`FHIR_Bundle_Generator_Conversion.md`** - FHIR bundle generation architecture
- **`MEDICARE_MAC_VALIDATION_ROUTES.md`** - Medicare MAC validation API routes
- **`CMS_Coverage_API_Integration.md`** - CMS Coverage API integration and validation builder

### 📋 `/compliance`
*Healthcare compliance and regulatory documentation*

- **`gm-wound-mac-validation.md`** - General Medicine & Wound Care MAC validation questionnaire
- **`pulm-wound-mac-validation.md`** - Pulmonology & Wound Care MAC validation questionnaire  
- **`Wound Care MAC Validation & Compliance Questionnaire.md`** - Comprehensive wound care compliance checklist

### ⚡ `/features`
*Feature specifications and implementation guides*

- **`MSC-MVP Product Request Flow.md`** - Complete 6-step product request workflow
- **`Clinical_Opportunity_Engine.md`** - Clinical decision support and revenue optimization

### 🔗 `/integration`
*Third-party integrations and external system connections*

- **`ECW_Integration_Guide.md`** - eClinicalWorks FHIR integration guide

### 🔒 `/security`
*Security implementations, access control, and compliance fixes*

- **`SECURITY_FIXES_SUMMARY.md`** - Comprehensive security and code quality improvements
- **`FINANCIAL_ACCESS_FIXES.md`** - Financial data access control implementation
- **`RBAC_IMPROVEMENTS_SUMMARY.md`** - Role-based access control enhancements

### 🎨 `/ui-ux`
*User interface and user experience documentation*

- **`ROLE_BASED_MENU_STRUCTURE.md`** - Complete role-based navigation system
- **`Sidebar_Menu_Structure.md`** - Menu structure by user role
- **`Menu_Route_Alignment_Analysis.md`** - Menu and route alignment analysis

### 📚 `/reference`
*Reference materials, catalogs, and standards*

- **`Core_Data_for_Interoperability_V4.md`** - USCDI v4 healthcare data standards
- **`Wound_Care_Products_Catalog.md`** - Complete MSC wound care products catalog

## 🔍 Quick Navigation

### By User Role
- **Healthcare Providers**: See `/features`, `/compliance`, `/ui-ux`
- **Office Managers**: See `/ui-ux`, `/security` (financial restrictions)
- **MSC Sales Reps**: See `/features`, `/reference` (product catalog)
- **Developers**: See `/api`, `/architecture`, `/integration`
- **Compliance Officers**: See `/compliance`, `/security`

### By Topic
- **FHIR Integration**: `/api/FHIR_*.md`
- **Medicare Compliance**: `/compliance/*-mac-validation.md`
- **Security & Access Control**: `/security/*.md`
- **Product Management**: `/reference/Wound_Care_Products_Catalog.md`
- **User Experience**: `/ui-ux/*.md`

## 🚀 Getting Started

1. **New Developers**: Start with `/api/FHIR_Implementation_Guide.md`
2. **Product Managers**: Review `/features/MSC-MVP Product Request Flow.md`
3. **Compliance Teams**: Check `/compliance/` for MAC validation requirements
4. **UI/UX Teams**: Explore `/ui-ux/` for navigation and menu structures

## 📝 Documentation Standards

### File Naming Convention
- Use descriptive, kebab-case filenames
- Include version numbers where applicable
- Use `.md` extension for Markdown files

### Content Structure
- Start with overview/summary
- Include table of contents for long documents
- Use consistent heading hierarchy
- Include code examples where relevant
- Add last updated dates

### Categories
- **API**: Technical integration documentation
- **Architecture**: System design and technical architecture
- **Compliance**: Healthcare regulatory requirements
- **Features**: Product features and workflows
- **Integration**: External system connections
- **Reference**: Catalogs, standards, and lookup materials
- **Security**: Access control and security implementations
- **UI/UX**: User interface and experience documentation

## 🔄 Maintenance

This documentation is actively maintained and updated as the system evolves. Each document includes:
- Last updated date
- Version information where applicable
- Change logs for major updates

## 📞 Support

For questions about specific documentation:
- **API Documentation**: Contact development team
- **Compliance Documentation**: Contact compliance team
- **UI/UX Documentation**: Contact product team
- **Security Documentation**: Contact security team

---

**Last Updated**: January 2025  
**Documentation Version**: 2.0.0  
**Project**: MSC Wound Care Portal 
