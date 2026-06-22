<?php

$host="localhost";
$port="5432";
$dbname="project_dwh";
$user="postgres";
$password="root";

try{

$conn = new PDO(
"pgsql:host=$host;port=$port;dbname=$dbname",
$user,
$password
);

echo "Koneksi PDO PostgreSQL Berhasil";

}catch(PDOException $e){

echo $e->getMessage();

}

?>