import json, shutil, subprocess, tempfile
from pathlib import Path
source=Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory() as tmp:
    root=Path(tmp)/'app';root.mkdir();(root/'tests').mkdir();(root/'api').mkdir()
    for name in ['lib.php','import-lib.php','tests/import.php','api/import-recipes.php']:
        shutil.copy(source/name,root/name)
    (root/'config.local.php').write_text("<?php return ['admin_password'=>'fixture-password','recipes_file'=>__DIR__.'/recipes.json','import_api_key_hash'=>hash('sha256',str_repeat('a',64))];")
    subprocess.run(['php',str(root/'tests/import.php')],check=True)
    for server, expected in [({'REQUEST_METHOD':'GET'},405),({'REQUEST_METHOD':'POST'},403),({'REQUEST_METHOD':'POST','HTTPS':'on'},401),({'REQUEST_METHOD':'POST','HTTPS':'on','HTTP_AUTHORIZATION':'Bearer '+('b'*64)},401),({'REQUEST_METHOD':'POST','HTTPS':'on','HTTP_AUTHORIZATION':'Bearer '+('a'*64)},415),({'REQUEST_METHOD':'POST','HTTPS':'on','HTTP_AUTHORIZATION':'Bearer '+('a'*64),'CONTENT_TYPE':'application/json','CONTENT_LENGTH':'999999999'},413)]:
        php="$_SERVER=array_merge($_SERVER,json_decode("+repr(json.dumps(server))+",true)); register_shutdown_function(function(){echo '\\nSTATUS:'.http_response_code();});require "+repr(str(root/'api/import-recipes.php'))+";"
        p=subprocess.run(['php','-r',php],capture_output=True,text=True,check=True)
        assert 'STATUS:'+str(expected) in p.stdout,p.stdout
    print('API checks passed: method, HTTPS, missing/wrong key, content type and size limits.')
