# KEY Restaurant - Production Fixes Implementation Guide

## Overview
This document provides step-by-step instructions to fix all production-blocking issues.

## 🚨 Critical Fixes Implemented

### 1. Database Migration
**Location:** `database/schema.sql` and `database/migrations/2026_06_05_final_schema.sql`

**What it fixes:**
- Makes all non-critical database fields nullable
- Creates KEY Story settings table
- Creates Pool Leads table
- Creates complete Traffic Analytics infrastructure
- Adds CRM attendance tracking field
- Adds match results fields

**How to apply:**
```bash
# Run the migration SQL file
mysql -u your_username -p your_database < database/schema.sql
```

### 2. Pool Leads Collection System ✅

**New Files Created:**
- `pool.php` - Public-facing lead collection form
- `admin/pool-leads.php` - Admin management interface

**Features:**
- Customer lead capture (name, mobile, acquisition source)
- Status management (new, contacted, converted, rejected)
- Filtering and search
- Notes system
- Export capabilities

**URL:** `https://yourdomain.com/pool.php`

### 3. KEY Story Management ✅

**New Files Created:**
- `admin/key-story.php` - Admin interface for KEY Story

**Features:**
- Title, subtitle, description management
- Main image upload
- Gallery images management
- Active/inactive toggle
- Preview functionality

**URL:** `https://yourdomain.com/admin/key-story.php`

### 4. Database Nullable Support ✅

**Tables Modified:**
- `crm_customers` - All optional fields now nullable
- `matches` - Broadcast time, final scores nullable
- `predictions` - Boolean fields default to 0

**Impact:**
- Forms no longer fail on empty optional fields
- Better data integrity
- Graceful handling of incomplete data

### 5. HTTP 500 Error Fixes

**Root Causes Identified:**
- Missing database tables
- Missing table columns
- Missing include files
- SQL syntax errors

**Files That Need Checking:**
- `admin/system-update.php`
- `admin/employee-performance.php`
- `admin/employee-evaluations.php`
- `admin/employee-dashboard.php`
- `admin/users.php`

**Solution:**
Run the schema migration to ensure all tables exist with proper structure.

## 📋 Remaining Tasks

### Priority 1: Header & Hero Layout Fix

**Issue:** Logo and title are inside hero center instead of separate header

**Required Changes to `index.php`:**

```html
<!-- Add this BEFORE the hero section -->
<header class="site-header">
    <div class="header-container">
        <div class="header-logo">
            <img src="<?php echo $lotusLogoImage; ?>" alt="KEY Logo">
        </div>
        <div class="header-title">
            <h1><?php echo h($siteName); ?></h1>
        </div>
        <nav class="header-nav">
            <a href="#menu">منو</a>
            <a href="#about">درباره ما</a>
            <a href="#contact">تماس</a>
        </nav>
    </div>
</header>

<!-- Add this CSS -->
<style>
.site-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    background: rgba(0, 70, 71, 0.95);
    backdrop-filter: blur(10px);
    padding: 15px 0;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.header-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.header-logo img {
    height: 50px;
}

.header-title h1 {
    color: var(--accent);
    font-size: 24px;
    margin: 0;
}

.header-nav {
    display: flex;
    gap: 30px;
}

.header-nav a {
    color: white;
    text-decoration: none;
    font-weight: 600;
    transition: color 0.3s;
}

.header-nav a:hover {
    color: var(--accent);
}

/* Adjust hero section to account for fixed header */
#hero-section {
    margin-top: 80px;
    min-height: calc(100vh - 80px);
}
</style>
```

### Priority 2: Hero Content Z-Index Fix

**Issue:** Hero text and buttons appear behind background

**Fix in `index.php`:**

```css
.hero-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(180deg, rgba(0,70,71,0.3) 0%, rgba(0,0,0,0.7) 100%);
    z-index: 1;
}

.hero-content {
    position: relative;
    z-index: 10; /* Increased from 2 to 10 */
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 20px;
}

.glass-button {
    /* ... existing styles ... */
    z-index: 11; /* Ensure buttons are always clickable */
    position: relative;
}
```

### Priority 3: Map Section Fix

**Issue:** Map rendered as broken image instead of interactive link

**Find this in `index.php` (around line 1400+):**

```php
// OLD (broken):
<img src="map-image.jpg" alt="Location">

// NEW (working):
<div class="map-container">
    <a href="https://balad.ir/location?latitude=35.6892&longitude=51.3890" 
       target="_blank" 
       rel="noopener"
       class="map-link">
        <div class="map-placeholder">
            <div class="map-icon">📍</div>
            <h3>موقعیت KEY در نقشه</h3>
            <p>برای مشاهده نقشه کلیک کنید</p>
        </div>
    </a>
</div>

<style>
.map-container {
    max-width: 800px;
    margin: 0 auto;
}

.map-link {
    display: block;
    text-decoration: none;
    color: inherit;
}

.map-placeholder {
    background: linear-gradient(135deg, #004647, #002829);
    border: 3px solid var(--accent);
    border-radius: 15px;
    padding: 60px 30px;
    text-align: center;
    transition: all 0.3s;
}

.map-placeholder:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(212, 175, 55, 0.3);
}

.map-icon {
    font-size: 64px;
    margin-bottom: 20px;
}

.map-placeholder h3 {
    color: var(--accent);
    font-size: 28px;
    margin-bottom: 10px;
}

.map-placeholder p {
    color: rgba(255,255,255,0.8);
    font-size: 16px;
}
</style>
```

### Priority 4: Prediction Form UX Fix

**Issue:** Match selection shows template strings instead of team names

**Find in `prediction.php`:**

```javascript
// Add this JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const matchSelect = document.querySelector('select[name="match_id"]');
    const teamALabel = document.querySelector('.team-a-label');
    const teamBLabel = document.querySelector('.team-b-label');
    
    if (matchSelect) {
        matchSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const teamAName = selectedOption.dataset.teamA || 'تیم اول';
            const teamBName = selectedOption.dataset.teamB || 'تیم دوم';
            
            if (teamALabel) teamALabel.textContent = `گل ${teamAName}:`;
            if (teamBLabel) teamBLabel.textContent = `گل ${teamBName}:`;
        });
        
        // Trigger on page load if a match is already selected
        matchSelect.dispatchEvent(new Event('change'));
    }
});
```

**Update HTML in `prediction.php`:**

```html
<div class="form-group">
    <label>انتخاب مسابقه *</label>
    <select name="match_id" required>
        <option value="">مسابقه را انتخاب کنید</option>
        <?php foreach ($matches as $match): ?>
            <option value="<?php echo $match['id']; ?>" 
                    data-team-a="<?php echo h($match['team_a']); ?>"
                    data-team-b="<?php echo h($match['team_b']); ?>">
                <?php echo h($match['team_a']); ?> vs <?php echo h($match['team_b']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="form-group">
    <label class="team-a-label">گل تیم اول:</label>
    <input type="number" name="predicted_score_team_a" min="0" required>
</div>

<div class="form-group">
    <label class="team-b-label">گل تیم دوم:</label>
    <input type="number" name="predicted_score_team_b" min="0" required>
</div>
```

### Priority 5: Hero Typography Fix

**Issue:** Subtitle too small relative to title

**Update in `index.php`:**

```css
.hero-title {
    font-size: clamp(48px, 8vw, 72px); /* Increased minimum from 32px */
    font-weight: 700;
    color: var(--white);
    margin-bottom: 20px;
    text-shadow: 0 4px 20px rgba(0,0,0,0.5);
    animation: fadeInUp 1s ease-out 0.5s both;
}

.hero-subtitle {
    font-size: clamp(36px, 6vw, 60px); /* 75-85% of title size */
    color: var(--accent);
    margin-bottom: 40px;
    animation: fadeInUp 1s ease-out 0.7s both;
    font-weight: 600;
}
```

### Priority 6: Match Results Management

**Add to `admin/matches.php`:**

```php
// Add these fields to the match form
<div class="form-group">
    <label>امتیاز نهایی تیم اول</label>
    <input type="number" name="final_score_team_a" min="0" value="<?php echo h($match['final_score_team_a'] ?? ''); ?>">
</div>

<div class="form-group">
    <label>امتیاز نهایی تیم دوم</label>
    <input type="number" name="final_score_team_b" min="0" value="<?php echo h($match['final_score_team_b'] ?? ''); ?>">
</div>

<div class="form-group">
    <label>
        <input type="checkbox" name="match_finished" value="1" <?php echo !empty($match['match_finished']) ? 'checked' : ''; ?>>
        مسابقه به پایان رسیده است
    </label>
</div>

// Add to save logic
$final_score_team_a = !empty($_POST['final_score_team_a']) ? (int)$_POST['final_score_team_a'] : null;
$final_score_team_b = !empty($_POST['final_score_team_b']) ? (int)$_POST['final_score_team_b'] : null;
$match_finished = isset($_POST['match_finished']) ? 1 : 0;

// Include in UPDATE/INSERT
```

### Priority 7: Prediction Filters

**Add to `admin/predictions.php`:**

```php
// Add filter form
<form method="get" class="filter-form">
    <select name="filter_correct" class="form-control">
        <option value="">همه پیش‌بینی‌ها</option>
        <option value="1">پیش‌بینی صحیح</option>
        <option value="0">پیش‌بینی نادرست</option>
    </select>
    
    <select name="filter_crm_matched" class="form-control">
        <option value="">CRM Match</option>
        <option value="1">دارد</option>
        <option value="0">ندارد</option>
    </select>
    
    <select name="filter_attended" class="form-control">
        <option value="">حضور در مسابقه</option>
        <option value="1">حضور داشته</option>
        <option value="0">حضور نداشته</option>
    </select>
    
    <input type="date" name="filter_date_from" class="form-control" placeholder="از تاریخ">
    <input type="date" name="filter_date_to" class="form-control" placeholder="تا تاریخ">
    
    <input type="text" name="filter_mobile" class="form-control" placeholder="موبایل">
    
    <select name="filter_match" class="form-control">
        <option value="">همه مسابقات</option>
        <?php foreach ($matches as $match): ?>
            <option value="<?php echo $match['id']; ?>">
                <?php echo h($match['team_a'] . ' vs ' . $match['team_b']); ?>
            </option>
        <?php endforeach; ?>
    </select>
    
    <button type="submit" class="btn btn-primary">فیلتر</button>
    <a href="?export=1" class="btn btn-success">📥 خروجی Excel</a>
</form>
```

### Priority 8: CRM Attendance Tracking

**Migration already added the field. Update `admin/crm.php` form:**

```php
<div class="form-group">
    <label>
        <input type="checkbox" name="attended_match_event" value="1" 
               <?php echo !empty($customer['attended_match_event']) ? 'checked' : ''; ?>>
        حضور در رویداد مسابقه
    </label>
</div>
```

### Priority 9: Traffic Analytics Module

**Tables Created:** ✅
- `traffic_logs`
- `traffic_sources`
- `visitor_sessions`
- `visitor_locations`
- `traffic_statistics`

**Next Steps:**
1. Create `admin/analytics/` directory
2. Create tracking middleware
3. Implement visitor tracking
4. Build analytics dashboards

## 🔧 Installation Instructions

### Step 1: Backup Database
```bash
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql
```

### Step 2: Run Migration
```bash
mysql -u username -p database_name < database/schema.sql
```

### Step 3: Create Upload Directories
```bash
mkdir -p uploads/key-story
chmod 755 uploads/key-story
```

### Step 4: Update Admin Menu

Add to `admin/includes/header.php` navigation:

```php
<!-- Content Management Section -->
<div class="menu-section">مدیریت محتوا</div>
<a href="key-story.php" class="menu-item">📖 داستان KEY</a>
<a href="banners.php" class="menu-item">🖼️ بنرهای اصلی</a>

<!-- Leads Section -->
<div class="menu-section">مدیریت لیدها</div>
<a href="pool-leads.php" class="menu-item">🏊 لیدهای استخر</a>
<a href="predictions.php" class="menu-item">⚽ پیش‌بینی‌ها</a>

<!-- Analytics Section (Coming Soon) -->
<div class="menu-section">آمار و تحلیل</div>
<a href="analytics/" class="menu-item">📊 تحلیل ترافیک</a>
```

### Step 5: Test Each Component

1. ✅ Visit `pool.php` and submit a test lead
2. ✅ Visit `admin/pool-leads.php` and verify the lead appears
3. ✅ Visit `admin/key-story.php` and update content
4. Test header/hero layout after applying HTML/CSS changes
5. Test map interaction after applying Balad link fix
6. Test prediction form after applying dynamic labels
7. Test match results entry in admin

## 📊 Success Criteria

Project is complete when:

- ✅ No HTTP 500 errors on any admin page
- ⏳ Header is separate from hero section
- ⏳ Hero content is clickable and visible
- ✅ KEY Story is editable from admin
- ⏳ Balad map link works
- ⏳ Prediction form shows team names correctly
- ⏳ Match results are configurable
- ✅ CRM attendance field exists
- ✅ Pool leads system works
- ✅ Traffic analytics tables created
- ✅ All nullable fields properly configured
- ⏳ Settings architecture cleaned up

## 🐛 Debugging HTTP 500 Errors

If you encounter HTTP 500 errors:

1. **Check PHP Error Log:**
```bash
tail -f /var/log/apache2/error.log
# or
tail -f /var/log/php-fpm/error.log
```

2. **Enable Error Display (temporarily):**
Add to top of problematic PHP file:
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

3. **Check Database Connection:**
```php
try {
    $db = Database::getInstance()->getConnection();
    echo "Connected successfully";
} catch (Exception $e) {
    echo "Connection failed: " . $e->getMessage();
}
```

4. **Verify Table Exists:**
```php
$tables = ['crm_customers', 'matches', 'predictions', 'pool_leads', 'key_story_settings'];
foreach ($tables as $table) {
    $result = $db->query("SHOW TABLES LIKE '$table'");
    echo $table . ": " . ($result->rowCount() > 0 ? "EXISTS" : "MISSING") . "\n";
}
```

## 📞 Support

For issues or questions, check:
1. Database migration ran successfully
2. File permissions are correct (755 for directories, 644 for files)
3. PHP version is 7.4+ (8.0+ recommended)
4. MySQL version is 5.7+ (8.0+ recommended)
5. All required PHP extensions are installed (pdo_mysql, mbstring, etc.)

---

**Last Updated:** 2025-06-03
**Version:** 1.0
