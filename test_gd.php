<?php
if (extension_loaded('gd')) {
    echo "✅ GD está habilitada correctamente<br>";
    echo "Versión GD: " . gd_info()['GD Version'];
} else {
    echo "❌ GD NO está habilitada";
}
?>