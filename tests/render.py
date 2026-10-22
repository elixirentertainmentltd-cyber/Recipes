"""Render real PHP templates against disposable data; never touch hosted recipe storage."""
import json
from html.parser import HTMLParser
from pathlib import Path
import shutil
import subprocess
import tempfile

SOURCE = Path(__file__).resolve().parents[1]
class Page(HTMLParser):
    def __init__(self, html):
        super().__init__(); self.ids=[]; self.h1=0; self.cards=0; self.checks=0; self.anchors=[]; self.feed(html)
    def handle_starttag(self, tag, attrs):
        a=dict(attrs)
        if 'id' in a: self.ids.append(a['id'])
        if tag=='h1': self.h1+=1
        if tag=='article' and 'recipe-card' in a.get('class','').split(): self.cards+=1
        if tag=='input' and a.get('type')=='checkbox': self.checks+=1
        if tag=='a' and a.get('href','').startswith('#'): self.anchors.append(a['href'][1:])

with tempfile.TemporaryDirectory() as directory:
    root=Path(directory)
    for file in SOURCE.glob('*.php'): shutil.copy(file,root/file.name)
    (root/'config.local.php').write_text("<?php return ['admin_password'=>'fixture-only', 'recipes_file'=>__DIR__.'/fixture.json'];")
    fixtures=[dict(id='a',slug='pasta',title='Pasta <script>alert(1)</script>',summary='Weeknight & easy',category='Dinner',cuisine='Italian',occasion='Weeknight',tags=['Quick & easy'],ingredients=['## Sauce','2 tomatoes','1 pinch salt'],method=['Chop tomatoes.','Cook the pasta.'],notes='Keep it simple.',prep_minutes=10,cook_minutes=20,servings=4,created_at='2026-10-22'),dict(id='b',slug='cake',title='Chocolate cake',summary='A favourite bake',category='Baking',cuisine='British',occasion='Party food',tags=['Vegetarian'],ingredients=['200g flour'],method=['Bake.'],prep_minutes=10,cook_minutes=40,servings=8,created_at='2026-10-21')]
    def render(file, query=None, admin=False, configured=True, recipes=fixtures):
        (root/'fixture.json').write_text(json.dumps(recipes))
        prefix=''
        if admin: prefix="session_start(); $_SESSION['recipes_admin']=true; session_write_close();"
        if not configured: (root/'config.local.php').write_text("<?php return ['recipes_file'=>__DIR__.'/fixture.json'];")
        php=prefix+"$_SERVER['REQUEST_METHOD']='GET'; $_GET=json_decode("+repr(json.dumps(query or {}))+",true); require "+repr(str(root/file))+";"
        result=subprocess.run(['php','-d','display_errors=stderr','-r',php],capture_output=True,text=True,check=True)
        assert not result.stderr,result.stderr
        html=result.stdout; page=Page(html)
        assert page.h1==1,(file,page.h1)
        assert len(page.ids)==len(set(page.ids)),page.ids
        assert all(a in page.ids for a in page.anchors),page.anchors
        assert 'Brought to you by You Batter Believe It' in html
        assert '<script>alert(1)</script>' not in html
        assert 'id="site-nav"' in html and 'Skip to main content' in html
        return html,page
    html,page=render('index.php'); assert page.cards==2 and 'Pasta &lt;script&gt;' in html
    for query in [{'q':'chocolate'},{'browse':'category:Baking'},{'tag':'Vegetarian'},{'q':'pasta','browse':'category:Dinner','tag':'Quick & easy'}]:
        html,page=render('index.php',query); assert page.cards==1,query
    html,page=render('index.php',{'q':'no such dish'}); assert page.cards==0 and 'Clear filters' in html
    html,page=render('index.php',recipes=[]); assert page.cards==0 and 'recipe-book-cover' in html
    html,page=render('recipe.php',{'slug':'pasta'}); assert page.checks==2 and 'id="method"' in html and 'Print recipe' in html
    html,page=render('recipe.php',{'slug':'missing'}); assert '404 / Missing recipe' in html
    html,page=render('admin.php'); assert 'name="password"' in html and 'name="ingredients"' not in html
    html,page=render('admin.php',admin=True); assert 'name="ingredients"' in html and 'Publish recipe' in html
    html,page=render('admin.php',{'edit':'a'},admin=True); assert 'Save changes' in html and 'Edit recipe' in html
    html,page=render('admin.php',configured=False); assert 'Setup required' in html
print('13 render scenarios passed: filters, empty library, recipe, 404, admin login/editor and setup.')
