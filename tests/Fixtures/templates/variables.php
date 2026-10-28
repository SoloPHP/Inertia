<?php

// Prints the variables the template sees, and whether $this is bound
echo json_encode([
    'variables' => array_keys(get_defined_vars()),
    'this' => isset($this),
], JSON_THROW_ON_ERROR);
