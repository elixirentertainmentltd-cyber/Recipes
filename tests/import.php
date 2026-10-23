<?php
// Run with an isolated copy of the application (see tests/import.py).
require __DIR__ . '/../lib.php';
require __DIR__ . '/../import-lib.php';
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function batch(array $rows, bool $dry = true) { return import_payload(json_encode(['recipes'=>$rows,'dry_run'=>$dry])); }
function rejects(callable $test) { try { $test(); } catch (InvalidArgumentException $e) { return; } throw new RuntimeException('Expected validation failure'); }
$r = ['title'=>'Test scones','category'=>'Baking','ingredients'=>['200g flour'],'instructions'=>['Mix and bake.'],'prep_minutes'=>10,'cook_minutes'=>20,'servings'=>4];
$file = recipe_data_file();
$old = ['id'=>'old','slug'=>'old','title'=>'Existing recipe','custom_field'=>'preserve exactly','cuisine'=>'British','occasion'=>'Party food','tags'=>['Legacy']];
file_put_contents($file,json_encode([$old]));
$before = file_get_contents($file);
$legacy = $r; $legacy['cuisine']='Old cuisine'; $legacy['occasion']='Old occasion'; $legacy['tags']=['Old tag'];
$normalised = batch([$legacy]);
foreach (['cuisine','occasion','tags'] as $field) check(!array_key_exists($field,$normalised['recipes'][0]),'Retired import field was stored');
$p = import_recipes(batch([$r])); check($p['ready']===1 && $p['added']===0,'Preview counts'); check(file_get_contents($file)===$before,'Preview wrote data');
$result = import_recipes(batch([$r],false)); check($result['added']===1 && $result['backup_created'],'Import failed');
$all=json_decode(file_get_contents($file),true); check($all[0]===$old,'Existing data altered'); check($all[1]['method']===['Mix and bake.'],'Instructions mapping');
$backups=glob(dirname(dirname(__DIR__)).'/elixir-recipes-backups/*.json'); check(count($backups)===1 && file_get_contents($backups[0])===$before,'Backup does not match original');
$count=count($all);$before=file_get_contents($file);
$duplicate=$r;$duplicate['title']=' TEST   SCONES! ';
$result=import_recipes(batch([$duplicate,$r],false));check($result['skipped']===2 && file_get_contents($file)===$before,'Duplicates changed store');
$new=$r;$new['title']='Another bake';$result=import_recipes(batch([$new,$new],false));check($result['added']===1 && $result['skipped']===1,'Within-batch duplicate');
foreach ([['category'=>'Unknown'],['prep_minutes'=>-1],['servings'=>'4'],['ingredients'=>[]],['instructions'=>['']],['role'=>'ADMIN'],['image_url'=>'javascript:alert(1)'],['image_base64'=>'not-an-image'],['image_url'=>'https://user:password@example.com/a.jpg']] as $change) rejects(fn()=>batch([array_merge($r,$change)]));
rejects(fn()=>import_payload('{bad json'));
rejects(fn()=>batch(array_fill(0,101,$r)));
rejects(fn()=>import_payload(json_encode(['recipes'=>[$r],'dry_run'=>'false'])));
$before=file_get_contents($file);$bad=$r;$bad['title']='Must not appear';$bad['category']='Invalid';rejects(fn()=>batch([$new,$bad],false));check(file_get_contents($file)===$before,'Invalid batch wrote data');
$image=$r;$image['title']='With image';$image['image_base64']=base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5WQAAAAASUVORK5CYII='));
$result=import_recipes(batch([$image],false));check($result['added']===1,'Image import failed');$rows=json_decode(file_get_contents($file),true);$last=end($rows);check(is_file(dirname(__DIR__).$last['image']),'Image file missing');check(!isset($last['_image_bytes']),'Internal image bytes persisted');
file_put_contents($file,'corrupt');try { import_recipes(batch([$r],false)); throw new LogicException('Corrupt store accepted'); } catch (RuntimeException $e) { check(!$e instanceof LogicException,'Corrupt store accepted'); } check(file_get_contents($file)==='corrupt','Corrupt store overwritten');
file_put_contents($file,$before);
$dir=dirname(dirname(__DIR__)).'/elixir-recipes-backups';foreach(glob($dir.'/*') as $p)unlink($p);rmdir($dir);file_put_contents($dir,'blocked');
try { import_recipes(batch([$image],false)); throw new LogicException('Missing backup accepted'); } catch (RuntimeException $e) { check(!$e instanceof LogicException,'Missing backup accepted'); }
check(file_get_contents($file)===$before,'Backup failure overwrote data');
echo "Import tests passed: preview, preservation, backups, duplicates, validation, images, corruption and backup failure.\n";

