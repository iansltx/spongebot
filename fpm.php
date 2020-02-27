<?php

require 'index.php';

echo json_encode([
    'message' => recapitalize($_GET['message'] ?? 'Please provide a message as "message" in your query string')
]);
