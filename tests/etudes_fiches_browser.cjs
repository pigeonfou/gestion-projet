const {chromium} = require('playwright');
const assert = require('node:assert/strict');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({viewport:{width:1536,height:1100}});
  page.setDefaultTimeout(15000);
  const errors=[]; page.on('pageerror', error=>errors.push(error.message));
  const root='http://127.0.0.1:8091';
  const url=root+'/projet.php?id=1&view=processus&step=2';
  await page.goto(root+'/login.php');
  await page.fill('#identifiant','admin'); await page.fill('#mot_de_passe','etudes-browser-only');
  await Promise.all([page.waitForURL('**/projets.php'),page.locator('button[type=submit]').click()]);
  await page.goto(url); await page.waitForSelector('.etudes-fiches');
  const rows=page.locator('.st-row');
  assert.equal(await rows.count(),5);
  await page.screenshot({path:'/tmp/etudes-initial.png',fullPage:true});
  assert.match(await page.locator('.ef-overview').innerText(),/260,00.*HT.*120,00.*TTC/);
  assert.match(await page.locator('.ef-missing').innerText(),/1 coût.*1 délai/);
  const second=rows.nth(1);
  await second.locator('.ef-toggle').click();
  await second.locator('[name="st_quantite[]"]').fill('3');
  await second.locator('[name="st_cout_unitaire[]"]').fill('50');
  assert.match(await second.locator('.ef-card-cost').innerText(),/150,00.*HT/);
  assert.match(await page.locator('.ef-save-status').innerText(),/non enregistrées/);
  await second.locator('[data-unknown-field=delai_jours]').check();
  assert.equal(await second.locator('[name="st_delai_jours[]"]').inputValue(),'inconnue');
  await page.screenshot({path:'/tmp/etudes-light.png',fullPage:true});
  await Promise.all([page.waitForNavigation(),page.locator('[type=submit][form=formSpecsTech]').click()]);
  assert.equal(await rows.nth(1).locator('[name="st_quantite[]"]').inputValue(),'3');
  assert.equal(await rows.nth(1).locator('[name="st_cout_unitaire[]"]').inputValue(),'50');
  assert.equal(await rows.nth(1).locator('[name="st_delai_jours[]"]').inputValue(),'inconnue');
  assert.equal(await rows.nth(2).locator('[name="st_parent_uid[]"]').inputValue(),'b'.repeat(32));
  assert.equal(await rows.nth(3).locator('.cp-taxe').inputValue(),'TTC');
  // The folded section opens again without changing data.
  const section=page.locator('.st-sf-block').first();
  await section.locator('.ef-section-toggle').click();
  assert.equal(await section.locator('.sf-table-wrap').isVisible(),false);
  await section.locator('.ef-section-toggle').click();
  // Child creation retains stable UIDs and persists a parent relation.
  await rows.first().locator('.ef-toggle').click();
  await rows.first().getByRole('button',{name:'+ Ajouter un sous-élément',exact:true}).click();
  const added=section.locator('.st-row').last();
  await added.locator('[name="st_description[]"]').fill('Connecteur de test');
  assert.equal(await added.locator('[name="st_parent_uid[]"]').inputValue(),'a'.repeat(32));
  await added.locator('[data-unknown-field=cout_unitaire]').check();
  await added.locator('[data-unknown-field=delai_jours]').check();
  await Promise.all([page.waitForNavigation(),page.locator('[type=submit][form=formSpecsTech]').click()]);
  assert.equal(await rows.count(),6);
  const child=rows.filter({has:page.locator('[name="st_description[]"][value="Connecteur de test"]')});
  assert.equal(await child.locator('[name="st_parent_uid[]"]').inputValue(),'a'.repeat(32));
  // Cross-function parent navigation must open the collapsed editor.
  await page.locator('.st-structure > summary').click();
  await page.locator('#st-structure-tree a').first().click();
  assert.equal(await rows.first().locator('[name="st_description[]"]').isVisible(),true);
  // Theme, accent and density come from the real settings form.
  const palettes=[];
  for (const [theme,accent,density] of [['light','ocean','comfortable'],['dark','indigo','compact']]) {
    await page.goto(root+'/admin/settings.php');
    await page.selectOption('[name=ui_theme]',theme);await page.selectOption('[name=ui_accent]',accent);await page.selectOption('[name=ui_density]',density);
    await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'Enregistrer l’apparence',exact:true}).click()]);
    await page.goto(url);await rows.nth(1).locator('.ef-toggle').click();
    const palette=await page.locator('.st-row').first().evaluate(el=>({bg:getComputedStyle(el).backgroundColor,fg:getComputedStyle(el).color,space:getComputedStyle(el).getPropertyValue('--ef-space')}));
    palettes.push(palette);await page.screenshot({path:'/tmp/etudes-'+theme+'-'+density+'.png',fullPage:true});
    await page.setViewportSize({width:390,height:844});
    const overflow=await page.locator('#formSpecsTech').evaluate(el=>el.scrollWidth>el.clientWidth+2);
    await page.screenshot({path:'/tmp/etudes-mobile-'+theme+'.png',fullPage:true});
    if (overflow) console.log('OVERFLOW', await page.locator('#formSpecsTech').evaluate(el=>({width:el.clientWidth,scroll:el.scrollWidth,items:[...el.querySelectorAll('*')].filter(n=>n.getBoundingClientRect().right>el.getBoundingClientRect().right+2 && n.getBoundingClientRect().width>0).slice(0,20).map(n=>({tag:n.tagName,cls:n.className,w:n.getBoundingClientRect().width,right:n.getBoundingClientRect().right,text:n.textContent.slice(0,80)}))})));
    assert.equal(overflow,false,'No horizontal scrolling in the estimate form');
    await page.screenshot({path:'/tmp/etudes-mobile-'+theme+'.png',fullPage:true});
    await page.setViewportSize({width:1536,height:1100});
  }
  assert.notEqual(palettes[0].bg,palettes[1].bg);assert.notEqual(palettes[0].space,palettes[1].space);
  // Deleting a parent still requires explicit confirmation; children survive.
  await rows.first().locator('.ef-toggle').click();
  await rows.first().locator('.btn-st-del').click();
  await page.getByRole('button',{name:'Annuler',exact:true}).click();
  assert.equal(await rows.count(),6);
  await rows.first().locator('.btn-st-del').click();
  await page.getByRole('button',{name:'Supprimer le parent et conserver les enfants',exact:true}).click();
  await Promise.all([page.waitForNavigation(),page.locator('[type=submit][form=formSpecsTech]').click()]);
  assert.equal(await rows.count(),5);
  assert.equal(await rows.filter({has:page.locator('[name="st_description[]"][value="Connecteur de test"]')}).locator('[name="st_parent_uid[]"]').inputValue(),'');
  // Reference picker and cost-free types still work with the card presentation.
  await rows.first().locator('.ef-toggle').click();
  await rows.first().locator('[name="st_type[]"]').selectOption('S.T.x.x');
  await page.locator('#st-reference-list button').first().click();
  assert.equal(await rows.first().locator('.ef-field-4').isVisible(),false);
  await Promise.all([page.waitForNavigation(),page.locator('[type=submit][form=formSpecsTech]').click()]);
  assert.equal(await rows.first().locator('[name="st_type[]"]').inputValue(),'S.T.x.x');
  assert.deepEqual(errors,[]);
  // Without JavaScript, the original server-rendered table remains usable.
  const fallback=await browser.newContext({javaScriptEnabled:false,storageState:await page.context().storageState()});
  const plain=await fallback.newPage();await plain.goto(url);
  assert.equal(await plain.locator('.st-table thead').isVisible(),true);
  assert.equal(await plain.locator('[name="st_description[]"]').first().isVisible(),true);
  await fallback.close();await browser.close();
  console.log('OK : real form save/reload, HT/TTC, unknowns, dynamic children, parent deletion, references, themes, density, mobile, no-JS fallback.');
})().catch(error=>{console.error(error);process.exit(1);});
