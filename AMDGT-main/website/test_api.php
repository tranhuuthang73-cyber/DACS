<?php
$_GET['action'] = 'graph_stats';
$_GET['dataset'] = 'C-dataset';
$_GET['max_drugs'] = 2;
$_GET['max_diseases'] = 2;
$_GET['max_proteins'] = 2;
ob_start();
require 'api/proxy.php';
$output = ob_get_clean();
echo $output;
