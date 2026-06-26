<?php
<<<<<<< HEAD
$serverName = "LAPTOP-FB8R0UH0";
=======
$serverName = "LAPTOP-EEPS0DEJ\ALSQLSERVER";
>>>>>>> 0abd9d4d5c2874abb677ffcabe7bc8ac4c06b8c9
$connectionOptions = array(
    "Database" => "SpotLight",
    "TrustServerCertificate" => true
);
$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    die(print_r(sqlsrv_errors(), true));
}
?>