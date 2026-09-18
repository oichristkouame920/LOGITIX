<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../models/AdminLogisticsModel.php';
if(!isPost()){redirect('admin/vehicules.php');} requireCsrf();
$idRaw=$_POST['id']??''; $id=null; if($idRaw!==''){ $v=filter_var($idRaw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if($v===false){setError('ID véhicule invalide.');redirect('admin/vehicules.php');} $id=(int)$v; }
$data=['nom'=>clean((string)($_POST['nom']??'')),'categorie'=>clean((string)($_POST['categorie']??'')),'immatriculation'=>clean((string)($_POST['immatriculation']??'')),'capacite'=>clean((string)($_POST['capacite']??'')),'statut'=>(string)($_POST['statut']??'')];
if($data['nom']===''||$data['categorie']===''||strlen($data['nom'])>100||strlen($data['categorie'])>50||strlen($data['immatriculation'])>30||strlen($data['capacite'])>50){setError('Champs véhicule invalides.');redirect('admin/vehicules.php');}
$data['immatriculation']=$data['immatriculation']!==''?$data['immatriculation']:null; $data['capacite']=$data['capacite']!==''?$data['capacite']:null;
try{(new AdminLogisticsModel())->saveVehicule($id,$data,(int)$_SESSION['user_id'],clientIp());rotateCsrfToken();setSuccess($id?'Véhicule mis à jour.':'Véhicule ajouté.');}catch(Throwable $e){error_log('LOGITIX vehicule save: '.$e->getMessage());setError(APP_ENV==='development'?'Action impossible : '.$e->getMessage():'Action véhicule impossible.');}redirect('admin/vehicules.php');
