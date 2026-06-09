<?php
<<<<<<< Updated upstream
$serverName = "LAPTOP-FB8R0UH0";
=======
$serverName = "EEPS0DEJ\AL";
>>>>>>> Stashed changes
$connectionOptions = array(
    "Database" => "SpotLight",
    "TrustServerCertificate" => true
);
$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    die(print_r(sqlsrv_errors(), true));
}
?>