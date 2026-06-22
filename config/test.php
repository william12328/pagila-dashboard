<?php

echo "<h2>PHP Version : " . phpversion() . "</h2>";

echo "<hr>";

if(function_exists("pg_connect")){
    echo "<h1 style='color:green'>✅ PostgreSQL Extension AKTIF</h1>";
}else{
    echo "<h1 style='color:red'>❌ PostgreSQL Extension BELUM AKTIF</h1>";
}

?>