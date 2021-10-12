<!DOCTYPE html> 
<html>
<head><meta charset="utf-8">
<link rel="icon" href="1440152911_globe green.ico" />
<title>xlfDiff2</title>
<script src='https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js'></script>
<script type="text/javascript">
// File determination
function Valid(){
	// ファイル未指定の場合はfalse
	var valid_flag = 0;
	if(document.form.file1.value == "" && document.form.file2.value == ""){
		alert('Please select a Before file & After file.');
		valid_flag = 1;
		return false;
	}
	if(document.form.file1.value == ""){
		alert('Please select a Before file.');
		valid_flag = 1;
		return false;
	}
	if (document.form.file2.value == ""){
		alert('Please select a After file.');
		valid_flag = 1;
		return false;
	}

	// zipまたはxlfを受け付ける。BeforeおよびAfterでそれぞれzipはzip、xlfはxlfでないとfalse
	re = new RegExp("\.(zip|xlf)$", "i");
	re_zip = new RegExp("\.zip$", "i");
	var before_file_flag = 'zip';
	var after_file_flag = 'zip';

	if(document.form.file1.value.search(re) == -1){
		alert('Choose zip or xlf format.');
		valid_flag = 1;
		return false;
	} else {
		if(document.form.file1.value.search(re_zip) == -1){
			before_file_flag = 'xlf';
		}
	}
	if(document.form.file2.value.search(re) == -1){
		alert('Choose zip or xlf format.');
		valid_flag = 1;
		return false;
	} else {
		if(document.form.file2.value.search(re_zip) == -1){
			after_file_flag = 'xlf';
		}
	}

	if (before_file_flag === after_file_flag){

	} else {
		alert('Specify the same file format.');
		return false;
	}

	// プログレスバー表示
	if (valid_flag === 0){
		$('#progress').show(500);
	}
}
// Alert when the specified bytes are exceeded
limit_size = 209715200;
$(function(){
	$('input[type=file]').change(function(){
	if($(this).val()){
		var file = $(this).prop('files')[0];
		file_size = file.size;
	}
		if(limit_size < file_size){
			alert('You cannot upload a file that is larger than 200MB.');
			$(this).val('');
		}
	});
});
</script>

<style type="text/css">
details {
    border: 1px solid #aaa;
    border-radius: 4px;
    padding: .5em .5em 0;
	width: 550px;
}

summary {
    font-weight: bold;
    margin: -.5em -.5em 0;
    padding: .5em;
}

details[open] {
    padding: .5em;
}

details[open] summary {
    border-bottom: 1px solid #aaa;
    margin-bottom: .5em;
}
</style>
</head>

<body>
<h1>xlfDiff2</h1>
<form name="form" enctype="multipart/form-data" method="post" onsubmit="return Valid();">
<p>Before:</p>
<input name="userfile[]" type="file" id="file1" accept=".zip">
</br>
<p>After:</p>
<input name="userfile[]" type="file" id="file2" accept=".zip">
</br></br>
<input type="submit" name="_upload" id="run" value="Upload">
</form>

<p hidden id="progress"><progress></progress></p>
</br>
<details>
	<summary>README</summary>
	<p><strong>XLF diff tool</strong></p>
</details>
<br />

<?php
set_time_limit(600); # 処理時間max
$cwd = getcwd();
$path = './temp';
if (file_exists($path)){
	// not doing
} else{
	mkdir($path, 0777);
}
if (isset($_POST['_upload'])) {
	$before_filename = $_FILES['userfile']['name'][0];
	$after_filename = $_FILES['userfile']['name'][1];
	$folder = date('Ymdhis');
	$proc_folder = "$path/$folder";
	mkdir($proc_folder, 0777);
	chdir($proc_folder);
	mkdir('before', 0777);
	mkdir('after', 0777);
	chdir($cwd);
	
	$before_file_fullpath = "$proc_folder/before/$before_filename";
	$after_file_fullpath = "$proc_folder/after/$after_filename";

	mainProcess($before_file_fullpath, $after_file_fullpath, $proc_folder);
	exit;
}

function mainProcess($before_file_fullpath, $after_file_fullpath, $proc_folder){
	$date = new DateTime();
	$result = $date->format('Y-m-dH_i_s') . '.xlsx';
	
	$xsl = 'xslt/XLIF2HTM_xsl2.0.xsl';
	$saxon = 'D:\\tool\\SaxonHE10-3J\\saxon-he-10.3.jar';
	$xlsxTemplate = "xliff_diff.xlsx";
	
	$before_dir = $proc_folder . '/' . 'before';
	$before_dir = realpath($before_dir);
	$after_dir = $proc_folder . '/' . 'after';
	$after_dir = realpath($after_dir);

	# beforeとafterのtmp_name
	$before_file_tmp = $_FILES['userfile']['tmp_name'][0];
	$after_file_tmp = $_FILES['userfile']['tmp_name'][1];

	# tmpファイル処理
	if (move_uploaded_file($before_file_tmp, $before_file_fullpath)){

	} else {
		//Error
		echo 'It could not be uploaded' . '<br />';
		exit;
	}
	if (move_uploaded_file($after_file_tmp, $after_file_fullpath)){

	} else {
		//Error
		echo 'It could not be uploaded' . '<br />';
		exit;
	}

	# xlfカウント用
	$before_xlf_c = 0;
	$after_xlf_c = 0;

	# アップロードされたファイルがzipかxlfかで処理を分ける
	if (preg_match("/^.+\.zip$/", $before_file_fullpath)){
		// アップロードされたbefore zipをunzip
		list($before_dir, $before_xlf_c) = unzip($before_dir, $before_file_fullpath);
	} else {
		$before_xlf_c = xlf_count($before_dir, $before_file_fullpath);
	}
	if (preg_match("/^.+\.zip$/", $after_file_fullpath)){
		// アップロードされたafter zipをunzip
		list($after_dir, $after_xlf_c) = unzip($after_dir, $after_file_fullpath);
	} else {
		$after_xlf_c = xlf_count($after_dir, $after_file_fullpath);
	}

	// beforeとafterのxlfファイル数が異なる場合は警告を出す
	if ($before_xlf_c != $after_xlf_c){
		echo '<div><font color=red>BeforeとAfterでxlfファイルの数が異なります。処理は継続しましたが、結果にずれが起きています。確認してください。</font></div>' . '<br />';
		echo '<strong>xlfファイルの数</strong><br />';
		echo 'Before: ' . $before_xlf_c . '<br />';
		echo 'After: ' . $after_xlf_c . '<br />';
	}

	// アップロードされたbefore zipとafter zip内のxlfをhtmlに変換
	toHtml($before_file_tmp, $saxon, $xsl, $before_dir, $before_file_fullpath);
	toHtml($after_file_tmp, $saxon, $xsl, $after_dir, $after_file_fullpath);

	// htmlを結果ファイル（エクセル）に貼り付け
	$cmd_diff = 'xlf2html-saxon.py' . ' ' . '"' . $before_dir . '"' . ' ' . '"' . $after_dir . '"' . ' ' . $result . ' ' . $xlsxTemplate;
	shell_exec($cmd_diff);

	// 結果ファイルのダウンロードリンク
	download($result, $proc_folder);
}

// ダウンロードリンク
function download($result, $proc_folder){
	rename($result, "$proc_folder/$result");
	echo '<hr size="1" color="#1DAF9E" width="565" align="left">';
	echo '<p><strong>Download: </strong><br />';
	echo "<a href=\"$proc_folder/$result\">" . "$result" . '</a>';
}

// Unzipしてxlfカウント
function unzip($dir, $file_fullpath){
	$file_fullpath = realpath($file_fullpath);
	$unzip_cmd = "7z x -o\"$dir\" \"$file_fullpath\"";
	shell_exec($unzip_cmd); # zip解凍
	unlink($file_fullpath); # zip削除

	$dir = $dir . '\\' . 'ja-JP'; // unzip後に「ja-JP」を追加

	// xlfファイル数をカウントする
	$xlf_c = 0;
	foreach (glob("$dir\\*.xlf") as $file_fullpath) {
		$xlf_c++;
	}

	return array($dir, $xlf_c);
}

// xlfカウントのみ
function xlf_count($dir, $file_fullpath){
	$file_fullpath = realpath($file_fullpath);

	// xlfファイル数をカウントする
	$xlf_c = 0;
	foreach (glob("$dir\\*.xlf") as $file_fullpath) {
		$xlf_c++;
	}

	return ($xlf_c);
}

// xlfをhtmlに変換
function toHtml($file_tmp, $saxon, $xsl, $dir, $file_fullpath){
	foreach (glob("$dir\\*.xlf") as $file_fullpath) {
		$file_fullpath_html = $file_fullpath . '.html';
		$cmd = 'java' . ' -jar ' . $saxon . ' -s:' . '"' . $file_fullpath . '"' . ' -xsl:' . $xsl . ' -o:' . '"' . $file_fullpath_html . '"';
		shell_exec($cmd);
	  }
}

?>

</body>
</html>