<?php
// KHO DỮ LIỆU - 1 tệp duy nhất. Chạy trên PHP 8+ (Hostinger có sẵn).
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])]);
session_start();
$D=__DIR__.'/storage';
if(!is_dir("$D/files"))mkdir("$D/files",0755,true);
if(!file_exists("$D/.htaccess"))file_put_contents("$D/.htaccess","Require all denied\n");
$db=new PDO("sqlite:$D/data.db");
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE IF NOT EXISTS users(id INTEGER PRIMARY KEY,name TEXT UNIQUE,pass TEXT,role TEXT,created TEXT);
CREATE TABLE IF NOT EXISTS files(id INTEGER PRIMARY KEY,name TEXT,cat TEXT,path TEXT,size INT,created TEXT);
CREATE TABLE IF NOT EXISTS notes(id INTEGER PRIMARY KEY,file_id INT,user TEXT,body TEXT,created TEXT);
CREATE TABLE IF NOT EXISTS logs(id INTEGER PRIMARY KEY,user TEXT,action TEXT,detail TEXT,ip TEXT,created TEXT);");

$_SESSION['t']??=bin2hex(random_bytes(16));
$u=$_SESSION['u']??null;
$adm=$u&&$u['role']==='admin';
$ip=$_SERVER['REMOTE_ADDR']??'';
$msg='';
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function L($a,$d=''){global $db,$u,$ip;
  $db->prepare("INSERT INTO logs(user,action,detail,ip,created)VALUES(?,?,?,?,?)")
     ->execute([$u['name']??'-',$a,$d,$ip,date('Y-m-d H:i:s')]);}
function cat($n){ // tự phân loại theo đuôi tệp
  $e=strtolower(pathinfo($n,PATHINFO_EXTENSION));
  $m=['Hình ảnh'=>'jpg jpeg png gif webp svg bmp','Tài liệu'=>'pdf doc docx txt md odt rtf ppt pptx',
      'Bảng tính'=>'xls xlsx csv ods','Video'=>'mp4 mov avi mkv webm','Âm thanh'=>'mp3 wav ogg flac m4a',
      'Tệp nén'=>'zip rar 7z tar gz','Mã nguồn'=>'html css js py php json xml sql'];
  foreach($m as $k=>$v)if(in_array($e,explode(' ',$v)))return $k;
  return 'Khác';}

// ---- TẢI TỆP (GET) ----
if($u&&isset($_GET['dl'])){
  $s=$db->prepare("SELECT * FROM files WHERE id=?");$s->execute([(int)$_GET['dl']]);$f=$s->fetch(PDO::FETCH_ASSOC);
  if(!$f||!is_file("$D/files/{$f['path']}"))die('Không tìm thấy tệp');
  L('tải tệp',$f['name']);
  header('Content-Type: application/octet-stream');
  header('Content-Disposition: attachment; filename*=UTF-8\'\''.rawurlencode($f['name']));
  header('Content-Length: '.filesize("$D/files/{$f['path']}"));
  readfile("$D/files/{$f['path']}");exit;}

// ---- XỬ LÝ BIỂU MẪU (POST) ----
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!hash_equals($_SESSION['t'],$_POST['t']??''))die('Phiên không hợp lệ');
  $a=$_POST['a']??'';
  $noUser=!$db->query("SELECT 1 FROM users")->fetch();
  if($a==='setup'&&$noUser){ // lần đầu: tạo tài khoản chủ máy
    $n=trim($_POST['n']);$p=$_POST['p'];
    if($n&&strlen($p)>=8){$db->prepare("INSERT INTO users(name,pass,role,created)VALUES(?,?,'admin',?)")
      ->execute([$n,password_hash($p,PASSWORD_DEFAULT),date('c')]);$msg='Đã tạo tài khoản chủ máy, hãy đăng nhập.';}
    else $msg='Mật khẩu tối thiểu 8 ký tự.';
  }elseif($a==='login'){
    $s=$db->prepare("SELECT * FROM users WHERE name=?");$s->execute([trim($_POST['n'])]);$r=$s->fetch(PDO::FETCH_ASSOC);
    if($r&&password_verify($_POST['p'],$r['pass'])){
      session_regenerate_id(true);$_SESSION['u']=$u=['name'=>$r['name'],'role'=>$r['role']];$adm=$u['role']==='admin';
      L('đăng nhập');
    }else{sleep(1);L('đăng nhập thất bại',trim($_POST['n']));$msg='Sai tài khoản hoặc mật khẩu.';}
  }elseif($u&&$a==='logout'){L('đăng xuất');session_destroy();header('Location: ./');exit;}
  elseif($u&&$a==='note'){ // người ngoài chỉ được góp ý
    $b=trim($_POST['body']);
    if($b){$db->prepare("INSERT INTO notes(file_id,user,body,created)VALUES(?,?,?,?)")
      ->execute([(int)$_POST['fid'],$u['name'],$b,date('Y-m-d H:i:s')]);L('gửi góp ý','tệp #'.(int)$_POST['fid']);$msg='Đã gửi góp ý cho chủ máy.';}
  }elseif($adm){ // các việc dưới đây CHỈ chủ máy làm được
    if($a==='upload'&&isset($_FILES['f'])){
      foreach((array)$_FILES['f']['name'] as $i=>$nm){
        if($_FILES['f']['error'][$i]!==0)continue;
        $p=bin2hex(random_bytes(12));
        move_uploaded_file($_FILES['f']['tmp_name'][$i],"$D/files/$p");
        $db->prepare("INSERT INTO files(name,cat,path,size,created)VALUES(?,?,?,?,?)")
          ->execute([basename($nm),cat($nm),$p,$_FILES['f']['size'][$i],date('Y-m-d H:i:s')]);
        L('tải lên',$nm);}
      $msg='Đã tải lên.';
    }elseif($a==='delfile'){
      $s=$db->prepare("SELECT * FROM files WHERE id=?");$s->execute([(int)$_POST['id']]);
      if($f=$s->fetch(PDO::FETCH_ASSOC)){@unlink("$D/files/{$f['path']}");
        $db->prepare("DELETE FROM files WHERE id=?")->execute([$f['id']]);L('xoá tệp',$f['name']);}
    }elseif($a==='adduser'){
      $n=trim($_POST['n']);$p=$_POST['p'];$role=($_POST['role']??'')==='admin'?'admin':'viewer';
      $ad=$db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
      if($role==='admin'&&$ad>=2)$msg='Tối đa 2 chủ máy.';
      elseif($n&&strlen($p)>=8){
        try{$db->prepare("INSERT INTO users(name,pass,role,created)VALUES(?,?,?,?)")
          ->execute([$n,password_hash($p,PASSWORD_DEFAULT),$role,date('c')]);L('cấp tài khoản',"$n ($role)");$msg='Đã cấp tài khoản.';}
        catch(Exception $e){$msg='Tên này đã tồn tại.';}
      }else $msg='Cần tên và mật khẩu tối thiểu 8 ký tự.';
    }elseif($a==='deluser'){
      $db->prepare("DELETE FROM users WHERE id=? AND name<>?")->execute([(int)$_POST['id'],$u['name']]);L('xoá tài khoản','#'.(int)$_POST['id']);
    }elseif($a==='delnote'){$db->prepare("DELETE FROM notes WHERE id=?")->execute([(int)$_POST['id']]);}
  }
  $u=$_SESSION['u']??null;$adm=$u&&$u['role']==='admin';
}

// ---- GIAO DIỆN ----
$T=h($_SESSION['t']);
$tab=$_GET['tab']??'files';
?><!doctype html><html lang="vi"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Kho dữ liệu</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Noto+Serif:wght@600;700&display=swap" rel="stylesheet">
<style>
:root{--a:#e3a46f}
*{box-sizing:border-box}
body{margin:0;padding:24px 12px;min-height:100vh;font-family:Inter,system-ui,sans-serif;color:#d8d8d8;
background:radial-gradient(circle at 20% 15%,#d9a977 0,#a9794d 38%,#5a3b22 100%) fixed}
.w{max-width:1000px;margin:0 auto;background:#0f0f0f;border:2px solid #d9b99a66;border-radius:20px;padding:20px 28px 32px;box-shadow:0 20px 60px #0009}
.top,.bar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;border-bottom:1px solid #222;padding-bottom:14px}
.logo{font-family:'Noto Serif',serif;font-weight:700;letter-spacing:.5px;color:#fff;font-size:14px}
nav a{color:#ddd;text-decoration:none;font-size:13px;margin:0 10px}nav a.on,nav a:hover{color:var(--a)}
.hero{text-align:center;padding:48px 0 12px}
.badge{display:inline-block;background:#1b1b1b;color:var(--a);font:700 11px 'Noto Serif',serif;letter-spacing:2px;padding:6px 12px;border-radius:6px}
h1,h2,h3{font-family:'Noto Serif',serif;color:#e8e8e8}
.hero h1{font-size:clamp(28px,5vw,44px);margin:18px 0 12px;line-height:1.2}
.hero p{color:#999;max-width:520px;margin:0 auto;font-size:14px;line-height:1.6}
.c{background:#161616;border:1px solid #262626;border-radius:12px;padding:16px;margin:16px 0;overflow-x:auto}
.n{max-width:380px;margin:24px auto}.n input{width:100%}
input,select,textarea{background:#0c0c0c;color:#eee;border:1px solid #333;border-radius:8px;padding:10px;margin:4px 0;font-family:inherit;font-size:14px;max-width:100%}
input:focus,select:focus{outline:none;border-color:var(--a)}
button{background:var(--a);color:#111;border:0;border-radius:999px;padding:9px 18px;font-family:inherit;font-weight:600;font-size:13px;cursor:pointer}
button:hover{filter:brightness(1.1)}
table{width:100%;border-collapse:collapse}td,th{padding:8px 6px;border-bottom:1px solid #222;text-align:left;font-size:13px}th{color:#888;font-weight:500}
a{color:var(--a)}
.m{background:#2a1f15;border:1px solid #5a3b22;color:var(--a);padding:10px;border-radius:8px;font-size:13px;margin-top:16px}
.pill{background:#1b1b1b;border:1px solid #2a2a2a;border-radius:999px;padding:5px 5px 5px 14px;font-size:12px;display:flex;align-items:center;gap:10px}
.pill form{margin:0}.pill button{padding:6px 12px}
</style></head><body><div class="w">
<?php if($msg)echo '<p class="m">'.h($msg).'</p>';

if(!$u){ ?>
  <header class="top"><div class="logo">⚖ KHO DỮ LIỆU</div></header>
  <section class="hero"><span class="badge">● KHO DỮ LIỆU</span>
  <h1>Nơi dữ liệu được<br>quản lý an toàn</h1>
  <p>Chỉ chủ máy mới được tải dữ liệu lên. Nhân viên được cấp quyền có thể xem, tải về và gửi góp ý.</p></section>
<?php
  if(!$db->query("SELECT 1 FROM users")->fetch()){ ?>
  <div class="c n"><h2>Thiết lập lần đầu: tạo tài khoản chủ máy</h2>
  <form method="post"><input type="hidden" name="t" value="<?=$T?>"><input type="hidden" name="a" value="setup">
  <input name="n" placeholder="Tên đăng nhập" required><br><input name="p" type="password" placeholder="Mật khẩu (≥ 8 ký tự)" required><br>
  <button>Tạo</button></form></div>
<?php }else{ ?>
  <div class="c n"><h2>Đăng nhập</h2>
  <form method="post"><input type="hidden" name="t" value="<?=$T?>"><input type="hidden" name="a" value="login">
  <input name="n" placeholder="Tên đăng nhập" required><br><input name="p" type="password" placeholder="Mật khẩu" required><br>
  <button>Đăng nhập</button></form></div>
<?php } echo '</div></body></html>';exit;}

L('xem '.$tab); // ghi nhật ký mỗi lần truy cập trang
?>
<header class="bar"><div class="logo">⚖ KHO DỮ LIỆU</div>
<nav><a class="<?=$tab==='files'?'on':''?>" href="?tab=files">Tệp</a><?php if($adm){?><a class="<?=$tab==='notes'?'on':''?>" href="?tab=notes">Góp ý</a><a class="<?=$tab==='users'?'on':''?>" href="?tab=users">Tài khoản</a><a class="<?=$tab==='logs'?'on':''?>" href="?tab=logs">Nhật ký</a><?php }?></nav>
<div class="pill"><span><?=h($u['name'])?> · <?=$adm?'chủ máy':'người xem'?></span>
<form method="post"><input type="hidden" name="t" value="<?=$T?>"><input type="hidden" name="a" value="logout"><button>Đăng xuất ↗</button></form></div></header>
<?php
if($tab==='files'||(!$adm&&$tab!=='files')){
  $q=trim($_GET['q']??'');$c=$_GET['c']??'';
  $sql="SELECT * FROM files WHERE name LIKE ?".($c?" AND cat=?":"")." ORDER BY cat,created DESC";
  $s=$db->prepare($sql);$s->execute($c?["%$q%",$c]:["%$q%"]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);
  $cats=$db->query("SELECT DISTINCT cat FROM files")->fetchAll(PDO::FETCH_COLUMN);
  if($adm){?><div class="c"><h3>Tải lên (tự động phân loại)</h3>
  <form method="post" enctype="multipart/form-data"><input type="hidden" name="t" value="<?=$T?>"><input type="hidden" name="a" value="upload">
  <input type="file" name="f[]" multiple required><button>Tải lên</button></form></div><?php }?>
  <div class="c"><form>🔎 <input name="q" value="<?=h($q)?>" placeholder="Tìm tên tệp">
  <select name="c"><option value="">Tất cả loại</option><?php foreach($cats as $k)echo '<option'.($k===$c?' selected':'').'>'.h($k).'</option>';?></select><button>Lọc</button></form>
  <table><tr><th>Tên</th><th>Loại</th><th>KB</th><th>Ngày</th><th></th></tr>
  <?php foreach($rows as $f){?><tr><td><?=h($f['name'])?></td><td><?=h($f['cat'])?></td><td><?=round($f['size']/1024)?></td><td><?=h($f['created'])?></td>
  <td><a href="?dl=<?=$f['id']?>">Tải</a>
  <?php if($adm){?><form method="post" style="display:inline" onsubmit="return confirm('Xoá tệp?')"><input type="hidden" name="t" value="<?=$T?>"><input type="hidden" name="a" value="delfile"><input type="hidden" name="id" value="<?=$f['id']?>"><button>Xoá</button></form><?php }?></td></tr>
  <tr><td colspan="5"><form method="post"><input type="hidden" name="t" value="<?=$T?>"><input type="hidden" name="a" value="note"><input type="hidden" name="fid" value="<?=$f['id']?>">
  <input name="body" placeholder="Góp ý / gợi ý chỉnh sửa gửi chủ máy" style="width:70%"><button>Gửi</button></form></td></tr>
  <?php }?></table></div>
<?php }elseif($adm&&$tab==='notes'){
  $r=$db->query("SELECT n.*,f.name fn FROM notes n LEFT JOIN files f ON f.id=n.file_id ORDER BY n.id DESC")->fetchAll(PDO::FETCH_ASSOC);?>
  <div class="c"><h3>Góp ý từ mọi người</h3><table><tr><th>Ngày</th><th>Người gửi</th><th>Tệp</th><th>Nội dung</th><th></th></tr>
  <?php foreach($r as $x)echo '<tr><td>'.h($x['created']).'</td><td>'.h($x['user']).'</td><td>'.h($x['fn']).'</td><td>'.h($x['body']).'</td><td><form method="post"><input type="hidden" name="t" value="'.$T.'"><input type="hidden" name="a" value="delnote"><input type="hidden" name="id" value="'.$x['id'].'"><button>Xoá</button></form></td></tr>';?></table></div>
<?php }elseif($adm&&$tab==='users'){
  $r=$db->query("SELECT * FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);?>
  <div class="c"><h3>Cấp tài khoản mới</h3><form method="post"><input type="hidden" name="t" value="<?=$T?>"><input type="hidden" name="a" value="adduser">
  <input name="n" placeholder="Tên đăng nhập" required> <input name="p" type="password" placeholder="Mật khẩu (≥ 8 ký tự)" required>
  <select name="role"><option value="viewer">Người xem</option><option value="admin">Chủ máy</option></select><button>Cấp</button></form></div>
  <div class="c"><table><tr><th>Tên</th><th>Vai trò</th><th>Tạo lúc</th><th></th></tr>
  <?php foreach($r as $x)echo '<tr><td>'.h($x['name']).'</td><td>'.h($x['role']).'</td><td>'.h($x['created']).'</td><td><form method="post" onsubmit="return confirm(\'Xoá?\')"><input type="hidden" name="t" value="'.$T.'"><input type="hidden" name="a" value="deluser"><input type="hidden" name="id" value="'.$x['id'].'"><button>Xoá</button></form></td></tr>';?></table></div>
<?php }elseif($adm&&$tab==='logs'){
  $r=$db->query("SELECT * FROM logs ORDER BY id DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);?>
  <div class="c"><h3>Nhật ký (300 dòng gần nhất)</h3><table><tr><th>Lúc</th><th>Ai</th><th>Hành động</th><th>Chi tiết</th><th>IP</th></tr>
  <?php foreach($r as $x)echo '<tr><td>'.h($x['created']).'</td><td>'.h($x['user']).'</td><td>'.h($x['action']).'</td><td>'.h($x['detail']).'</td><td>'.h($x['ip']).'</td></tr>';?></table></div>
<?php }?>
</div></body></html>
