# Changelog

All notable changes to `humweb/taggable-package` will be documented in this file.

## [1.0.0] - 2025-05-23

### Initial Release

#### Features

- **Core Tagging Functionality**

  - Tag any Eloquent model using the `HasTags` trait
  - Polymorphic relationships for flexible tagging
  - Support for single and multiple tag operations

- **User-Scoped Tags**

  - Personal tags per user (user_id scoped)
  - Global tags (shared across all users)
  - Mixed queries supporting both user and global tags
  - Configurable user scoping behavior

- **Tag Management**

  - Tag model with auto-generated slugs
  - Tag types for categorization

- **Tagging Operations**

  - `tag()` / `tagAsUser()` - Add tags
  - `untag()` / `untagAsUser()` - Remove tags
  - `retag()` / `retagAsUser()` - Replace all tags
  - `syncTags()` / `syncTagsAsUser()` - Sync tags
  - `attachTag()` / `detachTag()` - Single tag operations

- **Query Scopes**

  - `withAnyTags()` - Models with any of the given tags
  - `withAllTags()` - Models with all given tags
  - `withUserTags()` - Models with user-specific tags
  - `withGlobalTags()` - Models with global tags only
  - `withoutTags()` - Models without specific tags
  - `taggedWith()` - Simple single tag filter

- **Tag Utilities**

  - `hasTag()` / `hasUserTag()` / `hasGlobalTag()` - Check tag existence
  - `hasAnyTag()` - Check if model has any of given tags
  - `hasAllTags()` - Check if model has all given tags
  - `userTags()` / `globalTags()` - Get filtered tag collections

- **Tag Statistics**

  - Popular tags (overall, user-specific, or global)
  - Tag clouds with usage weights
  - Tag suggestions based on partial input
  - Unused tags detection

- **Events**

  - `TagAttached` - Fired when tag is attached
  - `TagDetached` - Fired when tag is detached
  - `TagsSynced` - Fired when tags are synced

- **Artisan Commands**

  - `tags:cleanup` - Remove unused tags
  - Options for user-specific or global cleanup

- **Additional Features**
  - Laravel Facade support
  - Configurable table names
  - Custom slug generation
  - Tag validation rules
  - Auto-delete unused tags (optional)
  - Factory support for testing

#### Requirements

- PHP 8.3+
- Laravel 10.x, 11.x, or 12.x
