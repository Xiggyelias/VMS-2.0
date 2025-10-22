# Auto-Deactivate Previous Vehicles Feature

## Overview
When a user adds a new vehicle, all their previous active vehicles are automatically deactivated, ensuring only one vehicle is active at a time. The UI updates instantly to reflect these changes without page reload.

## Business Logic

### Why Only One Active Vehicle?
- **Parking Pass Management**: Only one vehicle can be actively registered for parking at a time
- **Security**: Easier to track and manage which vehicle is currently authorized
- **Compliance**: Ensures users follow the single-active-vehicle policy
- **Clarity**: Clear visual indication of which vehicle is currently registered

## How It Works

### Backend Process (vehicle_operations.php)

#### Step 1: Identify Active Vehicles
```php
// Query all currently active vehicles for this user
SELECT vehicle_id FROM vehicles 
WHERE applicant_id = ? AND status = 'active'
```

#### Step 2: Deactivate Them
```php
// Set all active vehicles to inactive
UPDATE vehicles 
SET status = 'inactive', last_updated = NOW() 
WHERE applicant_id = ? AND status = 'active'
```

#### Step 3: Add New Vehicle as Active
```php
// Insert new vehicle with active status
INSERT INTO vehicles (applicant_id, regNumber, make, status, last_updated) 
VALUES (?, ?, ?, 'active', NOW())
```

#### Step 4: Return Complete Information
```php
$response = [
    'status' => 'success',
    'message' => 'Vehicle added successfully! (X previous vehicle(s) deactivated)',
    'vehicle' => [...],  // New vehicle data
    'deactivated_vehicle_ids' => [1, 2, 3],  // IDs of deactivated vehicles
    'deactivated_count' => 3  // Total number deactivated
];
```

### Frontend Updates (user-dashboard.php)

#### Visual Changes When Adding Vehicle:

1. **Previous Active Vehicles:**
   - Status badge changes from green "Active" to gray "Inactive"
   - Badge animates with scale effect (95% → 100%)
   - "Last Updated" time changes to "Just now"
   - Smooth 300ms transition

2. **New Vehicle:**
   - Appears at top of table
   - Fades in with slide-down animation
   - Shows green "Active" badge
   - Current timestamp displayed

3. **Success Message:**
   - Shows count of deactivated vehicles
   - Example: "Vehicle added successfully! (2 previous vehicle(s) deactivated)"

## User Experience Flow

### Scenario: User Has 1 Active Vehicle, Adds a New One

**Initial State:**
```
┌─────────────────────────────────────────────┐
│ Toyota Corolla | ABC123 | [Active] | ...   │
└─────────────────────────────────────────────┘
```

**User Clicks "Add New Vehicle"**
- Modal opens
- User fills: Make: "Honda Civic", Reg: "XYZ789"
- User clicks Submit

**Backend Processing:**
1. ✅ Validates registration number
2. ✅ Deactivates Toyota Corolla (ID: 1)
3. ✅ Adds Honda Civic as active
4. ✅ Returns both vehicle data and deactivated IDs

**UI Updates Instantly:**
```
┌─────────────────────────────────────────────┐
│ Honda Civic    | XYZ789 | [Active]   | ... │  ← New, fades in ✨
│ Toyota Corolla | ABC123 | [Inactive] | ... │  ← Status changes 🔄
└─────────────────────────────────────────────┘
```

**Visual Feedback:**
- Toyota's status badge animates from Active → Inactive
- Honda appears with fade-in animation at top
- Success message shows: "Vehicle added successfully! (1 previous vehicle(s) deactivated)"
- Modal closes after 800ms

### Scenario: User Has Multiple Active Vehicles

**Initial State:**
```
┌─────────────────────────────────────────────┐
│ Toyota Corolla | ABC123 | [Active] | ...   │
│ Honda Civic    | XYZ456 | [Active] | ...   │
│ Ford Focus     | DEF789 | [Active] | ...   │
└─────────────────────────────────────────────┘
```

**After Adding "BMW X5 | GHI012":**
```
┌─────────────────────────────────────────────┐
│ BMW X5         | GHI012 | [Active]   | ... │  ← New ✨
│ Toyota Corolla | ABC123 | [Inactive] | ... │  ← Changed 🔄
│ Honda Civic    | XYZ456 | [Inactive] | ... │  ← Changed 🔄
│ Ford Focus     | DEF789 | [Inactive] | ... │  ← Changed 🔄
└─────────────────────────────────────────────┘
```

**Message:** "Vehicle added successfully! (3 previous vehicle(s) deactivated)"

## Technical Implementation

### Database Transaction
All operations happen in a single transaction:
```php
$conn->begin_transaction();
try {
    // 1. Get IDs of active vehicles
    // 2. Deactivate them
    // 3. Insert new vehicle
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    throw $e;
}
```

**Benefits:**
- ✅ All-or-nothing operation
- ✅ Data consistency guaranteed
- ✅ Rollback on error

### Precise UI Updates

#### Using Specific Vehicle IDs:
```javascript
// Backend provides exact IDs that were deactivated
if (data.deactivated_vehicle_ids) {
    data.deactivated_vehicle_ids.forEach(vehicleId => {
        const row = document.getElementById('vehicle-' + vehicleId);
        // Update only this specific row
    });
}
```

**Benefits:**
- ✅ Only updates affected rows
- ✅ No unnecessary DOM manipulation
- ✅ Handles edge cases properly
- ✅ More efficient

#### Fallback for Compatibility:
```javascript
// If IDs not provided, update all active badges
tbody.querySelectorAll('tr').forEach(row => {
    const badge = row.querySelector('.status-badge.status-active');
    if (badge) {
        // Change to inactive
    }
});
```

## Visual Feedback

### Status Badge Animations

**Active → Inactive Transition:**
```css
transition: all 0.3s ease;
transform: scale(0.95); /* Shrink slightly */

/* Then back to normal */
transform: scale(1);
```

**Color Changes:**
- **Active:** Green badge (`status-active`)
- **Inactive:** Gray badge (`status-inactive`)

### New Vehicle Animation

**Fade-in with Slide:**
```javascript
// Initial state
tr.style.opacity = '0';
tr.style.transform = 'translateY(-10px)';

// Animate to visible
setTimeout(() => {
    tr.style.opacity = '1';
    tr.style.transform = 'translateY(0)';
}, 10);
```

**Effect:**
- Fades from invisible to visible
- Slides down 10px into position
- Takes 300ms
- Smooth, professional appearance

## Error Handling

### Registration Number Conflict
```php
// Check if registration already exists
if ($stmt->get_result()->num_rows > 0) {
    throw new Exception('This registration number is already registered.');
}
```

**UI Response:**
- Red error alert appears
- Form stays open
- User can correct the registration number
- No changes made to database

### Transaction Failure
```php
catch (Exception $e) {
    $conn->rollback();
    throw $e;
}
```

**Result:**
- All changes reverted
- Database remains consistent
- Error message shown to user

## Status Badge Styling

### CSS Classes

```css
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
}

.status-active {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status-inactive {
    background-color: #e2e3e5;
    color: #383d41;
    border: 1px solid #d6d8db;
}
```

## Benefits

### For Users
✅ **Clear Visual Feedback** - Instantly see which vehicle is active
✅ **No Confusion** - Only one vehicle active at a time
✅ **Smooth Transitions** - Professional animations
✅ **Informative Messages** - Know how many vehicles were deactivated

### For Administrators
✅ **Policy Enforcement** - Single active vehicle rule enforced
✅ **Easy Auditing** - Clear history of active/inactive status
✅ **Data Integrity** - Transactions ensure consistency

### For Developers
✅ **Robust Implementation** - Transactions prevent data corruption
✅ **Precise Updates** - Specific vehicle IDs ensure accuracy
✅ **Error Handling** - Graceful rollback on failures
✅ **Maintainable** - Clear separation of concerns

## Testing Scenarios

### Test 1: First Vehicle
- [ ] User has no vehicles
- [ ] Add first vehicle
- [ ] Should be active
- [ ] No deactivation message

### Test 2: Second Vehicle
- [ ] User has 1 active vehicle
- [ ] Add second vehicle
- [ ] First becomes inactive
- [ ] Second is active
- [ ] Message shows "1 previous vehicle(s) deactivated"

### Test 3: Multiple Active Vehicles
- [ ] User has 3 active vehicles
- [ ] Add new vehicle
- [ ] All 3 become inactive
- [ ] New one is active
- [ ] Message shows "3 previous vehicle(s) deactivated"

### Test 4: Duplicate Registration
- [ ] Try to add vehicle with existing registration number
- [ ] Error message appears
- [ ] No changes to database
- [ ] Form stays open

### Test 5: Visual Updates
- [ ] Status badges animate correctly
- [ ] New vehicle fades in smoothly
- [ ] Last updated times change
- [ ] No page reload occurs

## Configuration

### Maximum Vehicles Per User
Defined in `.env`:
```env
MAX_VEHICLES_PER_STUDENT=1
MAX_VEHICLES_PER_STAFF=5
MAX_VEHICLES_PER_GUEST=3
```

**Note:** Even if users can have multiple vehicles, only ONE can be active at a time.

## Future Enhancements

### Possible Improvements
- [ ] Add "Switch Active Vehicle" button
- [ ] Show activation history in timeline
- [ ] Email notification when vehicle deactivated
- [ ] Bulk activate/deactivate operations
- [ ] Scheduled activation (future date)
- [ ] Temporary activation (expires after X days)

## Summary

The auto-deactivate feature ensures:
1. **Only one vehicle is active** per user at any time
2. **Instant UI updates** show status changes
3. **Smooth animations** provide professional UX
4. **Data integrity** through transactions
5. **Clear feedback** about what changed

This creates a seamless, policy-compliant vehicle management system that's both user-friendly and administratively sound.
