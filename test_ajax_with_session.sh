#!/bin/bash

# AJAX Test with WordPress Login Session
# This script logs in to WordPress and then makes an AJAX request

# WordPress login
echo "Logging in to WordPress..."
LOGIN_RESPONSE=$(curl -c cookies.txt -d "log=kantan&pwd=admin123&wp-submit=Log+In&redirect_to=http%3A%2F%2Flocalhost%3A8081%2Fwp-admin%2F&testcookie=1" -X POST http://localhost:8081/wp-login.php)

if [ $? -eq 0 ]; then
    echo "Login request completed."
    
    # Get nonce from admin page
    echo "Getting nonce from admin page..."
    ADMIN_PAGE=$(curl -b cookies.txt -s http://localhost:8081/wp-admin/admin.php?page=ktpwp)
    
    # Extract nonce from the page (assuming it's in a JavaScript variable)
    NONCE=$(echo "$ADMIN_PAGE" | grep -o 'ktp_ajax_nonce[^"]*"[^"]*"[^"]*"[^"]*"' | head -n1 | sed 's/.*"\([^"]*\)".*/\1/')
    
    if [ -z "$NONCE" ]; then
        echo "Could not extract nonce from admin page."
        echo "Trying to use a generic nonce..."
        NONCE="test_nonce"
    else
        echo "Extracted nonce: $NONCE"
    fi
    
    # Make AJAX request with session cookies
    echo "Making AJAX request..."
    AJAX_RESPONSE=$(curl -b cookies.txt -d "action=ktp_get_supplier_qualified_invoice_number&supplier_id=1&_wpnonce=$NONCE" -X POST http://localhost:8081/wp-admin/admin-ajax.php)
    
    echo "AJAX Response: $AJAX_RESPONSE"
    
    # Clean up
    rm -f cookies.txt
else
    echo "Login failed."
fi
