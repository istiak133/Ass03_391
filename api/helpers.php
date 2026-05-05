<?php
// ============================================================
//  HELPER FUNCTIONS (helpers.php)
//  Common functions ja shob API file use korbe
//  Ekbar likhi, bar bar use kori — code duplication nai
// ============================================================


// -----------------------------------------
// 1. jsonResponse() — JSON Response Pathano
// -----------------------------------------
// Ki kore: Browser ke JSON format e response pathay
//
// Parameters:
//   $data = ki data pathabo (PHP array)
//   $statusCode = HTTP status code (default 200 = OK)
//
// Example use:
//   jsonResponse(['success' => true, 'message' => 'Done!']);
//   jsonResponse(['success' => false, 'error' => 'Not found'], 404);

function jsonResponse($data, $statusCode = 200) {
    // Browser ke boli: "ei response JSON format e"
    header('Content-Type: application/json');

    // HTTP status code set kori (200, 400, 404, 500, etc.)
    http_response_code($statusCode);

    // PHP array → JSON string e convert kore pathay
    echo json_encode($data);

    // Response pathanor por STOP — ar kono code run hobe na
    exit;
}


// -----------------------------------------
// 2. getRequestBody() — Browser er POST/PUT Data Receive Kora
// -----------------------------------------
// Ki kore: Browser theke je JSON data ashche seta read kore PHP array banay
//
// Return: Associative array (e.g., ['name' => 'Rahim', 'phone' => '017...'])
//         Jodi invalid JSON hole khali array return kore
//
// Example use:
//   $body = getRequestBody();
//   $name = $body['name'];   // 'Rahim'

function getRequestBody() {
    // Browser theke je raw JSON ashche seta read kori
    $raw = file_get_contents('php://input');

    // JSON string ke PHP array te convert kori
    // true = associative array hisabe return (object na)
    $data = json_decode($raw, true);

    // Jodi json_decode fail kore (invalid JSON) → khali array return
    // ?? = null coalescing operator: jodi null hole, right side er value nao
    return $data ?? [];
}


// -----------------------------------------
// 3. sanitize() — User Input Clean Kora (XSS Prevention)
// -----------------------------------------
// Ki kore: User er input theke dangerous HTML/JS characters remove kore
//
// Example:
//   sanitize('<script>alert("hack")</script>')
//   → '&lt;script&gt;alert(&quot;hack&quot;)&lt;/script&gt;'
//   Browser e eta plain text dekhabe — script run hobe na!
//
// Also trim() diye age-piche extra space remove kore

function sanitize($input) {
    // trim() = age-piche space remove
    // htmlspecialchars() = < > " ' & ke safe version e convert
    // ENT_QUOTES = single ar double duitai quote convert kore
    // UTF-8 = character encoding
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}


// -----------------------------------------
// 4. validateRequired() — Empty Field Check
// -----------------------------------------
// Ki kore: Bola fields khali ache kina check kore
//
// Parameters:
//   $data = form data array (e.g., ['name' => 'Rahim', 'phone' => ''])
//   $fields = kon kon fields required (e.g., ['name', 'phone', 'address'])
//
// Return: Error messages er array (khali hole → shob ok!)
//
// Example:
//   $errors = validateRequired(
//       ['name' => 'Rahim', 'phone' => '', 'address' => ''],
//       ['name', 'phone', 'address']
//   );
//   // $errors = ['phone is required', 'address is required']

function validateRequired($data, $fields) {
    $errors = [];

    foreach ($fields as $field) {
        // isset() = key ta ache kina data te
        // trim() = space remove korar por
        // === '' = strictly empty string kina
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            // field naam e underscore thakle space e convert kori (sundor message er jonno)
            $label = str_replace('_', ' ', $field);
            $errors[] = "$label is required";
        }
    }

    return $errors;  // Khali array hole → shob fields filled — kono error nai
}


// -----------------------------------------
// 5. validatePhone() — Phone Number Validation
// -----------------------------------------
// Ki kore: Phone number e shudhu digit (0-9) ache kina check kore
//
// Return: true = valid, false = invalid
//
// Example:
//   validatePhone('01711111111') → true
//   validatePhone('017-abc')     → false
//   validatePhone('')            → false

function validatePhone($phone) {
    // preg_match = regex pattern match
    // /^[0-9]+$/ = shuru theke shesh porjonto shudhu 0-9 digit, minimum 1 ta
    return preg_match('/^[0-9]+$/', $phone) === 1;
}


// -----------------------------------------
// 6. validateDate() — Date Validation
// -----------------------------------------
// Ki kore: 2 ta check —
//   (a) Date format valid kina (YYYY-MM-DD)
//   (b) Past date kina (aaj er aage hole invalid)
//
// Return: Array — ['valid' => true/false, 'error' => 'message if invalid']
//
// Example:
//   validateDate('2026-03-15') → ['valid' => true]
//   validateDate('2025-01-01') → ['valid' => false, 'error' => 'Date cannot be in the past']
//   validateDate('abc')        → ['valid' => false, 'error' => 'Invalid date format']

function validateDate($date) {
    // Check 1: Format valid kina?
    // strtotime() date string ke timestamp e convert kore
    // Invalid date hole false return kore
    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return ['valid' => false, 'error' => 'Invalid date format'];
    }

    // Check 2: Past date kina?
    // date('Y-m-d') = aajker date (e.g., '2026-03-10')
    // Jodi selected date < today → past date → not allowed
    $today = date('Y-m-d');

    if ($date < $today) {
        return ['valid' => false, 'error' => 'Date cannot be in the past'];
    }

    // Shob check pass!
    return ['valid' => true];
}


// -----------------------------------------
// 7. validatePhoneSum() — Phone Last 3 Digits Sum Check
// -----------------------------------------
// Ki kore: Phone number er last 3 digit sum = 21 kina check kore
// USED FOR: Waiting list confirmation validation
//
// Return: true = sum is 21, false = sum is not 21
//
// Example:
//   validatePhoneSum('01711000123') → 1+2+3 = 6 → false
//   validatePhoneSum('01799999999') → 9+9+9 = 27 → false
//   validatePhoneSum('01711111159') → 1+5+9 = 15 → false
//   validatePhoneSum('01711111555') → 5+5+5 = 15 → false
//   validatePhoneSum('01711111789') → 7+8+9 = 24 → false
//   validatePhoneSum('01711111696') → 6+9+6 = 21 → TRUE ✓

function validatePhoneSum($phone) {
    // Last 3 character extract
    $last3 = substr($phone, -3);
    
    // Each digit sum
    $sum = 0;
    for ($i = 0; $i < 3; $i++) {
        $sum += (int)$last3[$i];
    }
    
    // Sum = 21 kina check
    return $sum === 21;
}
