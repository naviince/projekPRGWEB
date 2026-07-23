<?php
$serverName = "LAPTOP-FB8R0UH0";
<<<<<<< HEAD
$serverName = "ELVINA-PARAMITA";
$serverName = "SATYAA\SATYASERVER";

=======
>>>>>>> 56011b38cf3c711765aa03cc32fc259cd8ce406f
$connectionOptions = array(
    "Database" => "SpotLight",
    "TrustServerCertificate" => true
);
$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    die(print_r(sqlsrv_errors(), true));
}
?>