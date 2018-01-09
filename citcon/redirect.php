<?php
@header('Content-type: text/html;charset=UTF-8');
$contents= base64_decode($_GET["res"]);
$content=html_entity_decode($contents);
$result = json_decode($content);
if ($result->{'result'} == 'success') {
    echo "<script>window.location='".$result->{'url'}."';</script>";
} else {
    echo "Error has occurred, please press the back button";
}
?>
