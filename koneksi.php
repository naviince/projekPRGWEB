<?php
<<<<<<< HEAD
$serverName = "SATYAA\SATYASERVER";
=======
$serverName = "LAPTOP-EEPS0DEJ\ALSQLSERVER";
>>>>>>> f79fb258aceabc6eb629a82d51aaf7f95ffc23a6
$connectionOptions = array(
    "Database" => "SpotLight",
    "TrustServerCertificate" => true
);
$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    die(print_r(sqlsrv_errors(), true));
}
?>