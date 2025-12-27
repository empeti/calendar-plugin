# PowerShell script to generate test appointments for mpeti-booking-calendar
# Usage: .\generate-test-appointments.ps1 -Count 10

param(
    [Parameter(Mandatory=$true)]
    [int]$Count
)

# Configuration
$ScriptPath = Split-Path -Parent $MyInvocation.MyCommand.Path
$WpPath = Join-Path $ScriptPath "app\public"

# Check if WP-CLI is available
$wpCli = "wp"
if (-not (Get-Command $wpCli -ErrorAction SilentlyContinue)) {
    Write-Host "Error: WP-CLI not found. Please install WP-CLI or ensure it's in your PATH." -ForegroundColor Red
    exit 1
}

# Change to WordPress directory
Push-Location $WpPath

Write-Host "Generating $Count test appointments..." -ForegroundColor Green

# Get all services
Write-Host "Fetching services..." -ForegroundColor Yellow
$servicesJson = & wp post list --post_type=mbc_service --post_status=publish --format=json --fields=ID,post_title
$services = $servicesJson | ConvertFrom-Json

if ($services.Count -eq 0) {
    Write-Host "Error: No services found. Please create services first." -ForegroundColor Red
    Pop-Location
    exit 1
}

Write-Host "Found $($services.Count) service(s)" -ForegroundColor Green

# Get all staff members
Write-Host "Fetching staff members..." -ForegroundColor Yellow
$staffJson = & wp post list --post_type=mbc_staff --post_status=publish --format=json --fields=ID,post_title
$staff = $staffJson | ConvertFrom-Json

if ($staff.Count -eq 0) {
    Write-Host "Error: No staff members found. Please create staff members first." -ForegroundColor Red
    Pop-Location
    exit 1
}

Write-Host "Found $($staff.Count) staff member(s)" -ForegroundColor Green

# Sample first names
$firstNames = @(
    "John", "Jane", "Michael", "Sarah", "David", "Emily", "Robert", "Jessica",
    "William", "Ashley", "James", "Amanda", "Christopher", "Melissa", "Daniel",
    "Michelle", "Matthew", "Kimberly", "Anthony", "Amy", "Mark", "Angela",
    "Donald", "Stephanie", "Steven", "Nicole", "Paul", "Elizabeth", "Andrew",
    "Helen", "Joshua", "Sandra", "Kenneth", "Donna", "Kevin", "Carol", "Brian",
    "Ruth", "George", "Sharon", "Edward", "Michelle", "Ronald", "Laura"
)

# Sample last names
$lastNames = @(
    "Smith", "Johnson", "Williams", "Brown", "Jones", "Garcia", "Miller", "Davis",
    "Rodriguez", "Martinez", "Hernandez", "Lopez", "Wilson", "Anderson", "Thomas",
    "Taylor", "Moore", "Jackson", "Martin", "Lee", "Thompson", "White", "Harris",
    "Clark", "Lewis", "Robinson", "Walker", "Young", "Allen", "King", "Wright",
    "Scott", "Torres", "Nguyen", "Hill", "Flores", "Green", "Adams", "Nelson"
)

# Sample email domains
$emailDomains = @(
    "gmail.com", "yahoo.com", "hotmail.com", "outlook.com", "icloud.com",
    "aol.com", "mail.com", "protonmail.com", "live.com", "msn.com"
)

# Sample phone prefixes
$phonePrefixes = @("555", "555", "555", "555", "555", "555", "555", "555", "555", "555")

# Sample notes
$sampleNotes = @(
    "First time customer",
    "Returning customer",
    "Please call when arrived",
    "Customer prefers morning appointments",
    "Has allergies - please note",
    "VIP customer",
    "Requires wheelchair access",
    "Prepaid appointment",
    "",
    "",
    ""
)

# Status options
$statuses = @("pending", "confirmed", "pending", "confirmed", "pending")

# Function to generate random date within next 90 days
function Get-RandomDate {
    $startDate = Get-Date
    $endDate = $startDate.AddDays(90)
    $randomDays = Get-Random -Minimum 0 -Maximum ([int]($endDate - $startDate).TotalDays)
    $randomDate = $startDate.AddDays($randomDays)
    return $randomDate.ToString("yyyy-MM-dd")
}

# Function to get available timeslots for a staff member on a date
function Get-AvailableTimeslots {
    param(
        [string]$Date,
        [int]$StaffId
    )
    
    # Use WP-CLI eval to get timeslots via PHP
    $phpCode = @"
`$date = '$Date';
`$staff_id = $StaffId;
`$date_obj = new DateTime(`$date);
`$day_of_week = (int)`$date_obj->format('w');

global `$wpdb;
`$table = `$wpdb->prefix . 'mbc_timeslots';
`$timeslots = `$wpdb->get_results(
    `$wpdb->prepare(
        'SELECT start_time, end_time, capacity FROM ' . `$table . ' WHERE weekday = %d AND staff_id = %d AND is_active = 1',
        `$day_of_week,
        `$staff_id
    ),
    ARRAY_A
);

`$all_slots = array();
if (`$timeslots) {
    foreach (`$timeslots as `$slot) {
        `$start = strtotime(`$date . ' ' . `$slot['start_time']);
        `$end = strtotime(`$date . ' ' . `$slot['end_time']);
        `$current = `$start;
        while (`$current < `$end) {
            `$all_slots[] = date('H:i', `$current);
            `$current += 30 * 60; // 30 minutes
        }
    }
}

// Get booked appointments
`$args = array(
    'post_type' => 'mbc_appointment',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'meta_query' => array(
        array('key' => 'staff_id', 'value' => `$staff_id),
        array('key' => 'appointment_date', 'value' => `$date),
    ),
);
`$booked = get_posts(`$args);
`$booked_times = array();
foreach (`$booked as `$apt) {
    `$time = get_post_meta(`$apt->ID, 'appointment_time', true);
    if (`$time) {
        `$booked_times[] = `$time;
    }
}

// Filter available slots
`$available = array();
foreach (`$all_slots as `$time) {
    `$count = count(array_filter(`$booked_times, function(`$t) use (`$time) { return `$t === `$time; }));
    `$capacity = 1;
    foreach (`$timeslots as `$slot) {
        if (strtotime(`$date . ' ' . `$time) >= strtotime(`$date . ' ' . `$slot['start_time']) &&
            strtotime(`$date . ' ' . `$time) < strtotime(`$date . ' ' . `$slot['end_time'])) {
            `$capacity = (int)`$slot['capacity'];
            break;
        }
    }
    if (`$count < `$capacity) {
        `$available[] = `$time;
    }
}

echo json_encode(`$available);
"@
    
    $result = & wp eval "$phpCode" 2>$null
    if (-not $result -or $result -eq "[]") {
        return @()
    }
    
    $slots = $result | ConvertFrom-Json
    return $slots
}

# Function to find an available slot
function Find-AvailableSlot {
    param(
        [int]$MaxAttempts = 50
    )
    
    for ($i = 0; $i -lt $MaxAttempts; $i++) {
        $randomStaff = $staff | Get-Random
        $randomDate = Get-RandomDate
        $availableSlots = Get-AvailableTimeslots -Date $randomDate -StaffId $randomStaff.ID
        
        if ($availableSlots.Count -gt 0) {
            $randomTime = $availableSlots | Get-Random
            return @{
                StaffId = $randomStaff.ID
                Date = $randomDate
                Time = $randomTime
            }
        }
    }
    
    return $null
}

# Generate appointments
$successCount = 0
$failCount = 0

for ($i = 1; $i -le $Count; $i++) {
    Write-Host "Creating appointment $i/$Count..." -ForegroundColor Cyan
    
    # Generate random customer data
    $firstName = $firstNames | Get-Random
    $lastName = $lastNames | Get-Random
    $fullName = "$firstName $lastName"
    $emailDomain = $emailDomains | Get-Random
    $email = "$($firstName.ToLower()).$($lastName.ToLower())@$emailDomain"
    $phonePrefix = $phonePrefixes | Get-Random
    $phoneSuffix = Get-Random -Minimum 1000 -Maximum 9999
    $phone = "$phonePrefix-$phoneSuffix"
    $notes = $sampleNotes | Get-Random
    $status = $statuses | Get-Random
    
    # Find available slot
    $slot = Find-AvailableSlot
    
    if (-not $slot) {
        Write-Host "  Warning: Could not find available slot. Skipping..." -ForegroundColor Yellow
        $failCount++
        continue
    }
    
    # Get a service that the staff member provides
    $staffServices = & wp post meta get $slot.StaffId staff_services --format=json 2>$null | ConvertFrom-Json
    $serviceId = 0
    
    if ($staffServices -and $staffServices.Count -gt 0) {
        $serviceId = $staffServices | Get-Random
    } else {
        # If staff has no assigned services, pick a random service
        $serviceId = ($services | Get-Random).ID
    }
    
    # Create appointment post
    $postTitle = "$($slot.Date) - $fullName"
    $postId = & wp post create --post_type=mbc_appointment --post_status=publish --post_title="$postTitle" --format=json 2>$null | ConvertFrom-Json
    
    if (-not $postId -or $postId.ID -eq 0) {
        Write-Host "  Error: Failed to create appointment post" -ForegroundColor Red
        $failCount++
        continue
    }
    
    # Set appointment metadata
    & wp post meta update $postId.ID customer_name "$fullName" >$null 2>&1
    & wp post meta update $postId.ID customer_email "$email" >$null 2>&1
    & wp post meta update $postId.ID customer_phone "$phone" >$null 2>&1
    & wp post meta update $postId.ID appointment_date "$($slot.Date)" >$null 2>&1
    & wp post meta update $postId.ID appointment_time "$($slot.Time)" >$null 2>&1
    & wp post meta update $postId.ID appointment_status "$status" >$null 2>&1
    & wp post meta update $postId.ID service_id $serviceId >$null 2>&1
    & wp post meta update $postId.ID staff_id $($slot.StaffId) >$null 2>&1
    & wp post meta update $postId.ID appointment_datetime "$($slot.Date) $($slot.Time)" >$null 2>&1
    
    if ($notes) {
        & wp post meta update $postId.ID notes "$notes" >$null 2>&1
    }
    
    Write-Host "  Created: $fullName - $($slot.Date) at $($slot.Time) (Status: $status)" -ForegroundColor Green
    $successCount++
    
    # Small delay to avoid overwhelming the system
    Start-Sleep -Milliseconds 100
}

Write-Host "`nGeneration complete!" -ForegroundColor Green
Write-Host "  Success: $successCount" -ForegroundColor Green
Write-Host "  Failed: $failCount" -ForegroundColor $(if ($failCount -gt 0) { "Yellow" } else { "Green" })

Pop-Location

