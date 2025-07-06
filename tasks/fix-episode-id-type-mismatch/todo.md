# Fix Episode ID Type Mismatch Error

## Problem Statement
Order submission was failing with a TypeError because:
- `PatientManufacturerIVREpisode` uses UUID strings for IDs (e.g., "9f53731b-06a7-44c8-9448-180d28b5581e")
- `UnifiedFieldMappingService::mapEpisodeToTemplate()` expected an integer for the episode ID
- `DataExtractor::extractEpisodeData()` also expected an integer

## Root Cause
The field mapping services were designed for a different Episode model that used integer IDs, but the system now uses PatientManufacturerIVREpisode with UUID strings.

## Todo List
- [x] Update UnifiedFieldMappingService to accept string episode ID
- [x] Update DataExtractor to accept string episode ID
- [x] Fix Episode model reference to PatientManufacturerIVREpisode
- [x] Update cache key handling for string IDs
- [x] Add productRequests relationship to PatientManufacturerIVREpisode
- [x] Update import statements in DataExtractor
- [x] Update extractEpisodeFields to match PatientManufacturerIVREpisode fields

## Changes Made

### 1. UnifiedFieldMappingService.php
- Changed `mapEpisodeToTemplate()` parameter from `?int $episodeId` to `?string $episodeId`
- This allows the method to accept UUID strings

### 2. DataExtractor.php
- Changed `extractEpisodeData()` parameter from `int $episodeId` to `string $episodeId`
- Updated model reference from `Episode::` to `PatientManufacturerIVREpisode::`
- Updated import statement to use correct models
- Cache key generation already worked with strings, no change needed

### 3. PatientManufacturerIVREpisode.php
- Added missing `productRequests()` relationship method
- This relationship is needed by the DataExtractor

### 4. extractEpisodeFields method
- Updated to use fields that exist on PatientManufacturerIVREpisode
- Added ivr_status and patient_display_id fields
- Used episode ID as episode_number since UUIDs don't have separate numbers

## Review

The implementation successfully fixes the type mismatch error. The field mapping services now:
1. Accept UUID strings for episode IDs
2. Use the correct PatientManufacturerIVREpisode model
3. Have proper relationships defined
4. Extract the correct fields from the episode

This allows order submission to proceed without the type error, maintaining compatibility with the UUID-based episode system.