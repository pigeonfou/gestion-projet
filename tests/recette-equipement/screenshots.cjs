const {chromium}=require('playwright');
const fs=require('node:fs');
(async()=>{
 const out=process.argv[2], result=JSON.parse(fs.readFileSync(out+'/resultat_recette.json'));const pid=result.project_id;
 const browser=await chromium.launch();const page=await browser.newPage({viewport:{width:1536,height:1100}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://127.0.0.1:8091/login.php');await page.fill('#identifiant','admin');await page.fill('#mot_de_passe','atlas12-fixture-only');await Promise.all([page.waitForURL('**/projets.php'),page.locator('button[type=submit]').click()]);
 await page.goto(`http://127.0.0.1:8091/projet.php?id=${pid}&view=processus&step=2`);
 const count=await page.locator('.st-row').count();if(count!==30)throw Error('30 ST attendues');
 await page.screenshot({path:out+'/apercu-etudes-30-ST.png',fullPage:true});
 await page.goto(`http://127.0.0.1:8091/projet.php?id=${pid}&view=processus&step=9`);await page.screenshot({path:out+'/apercu-cloture.png',fullPage:true});
 await page.goto(`http://127.0.0.1:8091/production.php?projet_id=${pid}`);await page.screenshot({path:out+'/apercu-production.png',fullPage:true});
 await page.goto('http://127.0.0.1:8091/inventaire.php');await page.waitForSelector('[data-open]');await page.screenshot({path:out+'/apercu-catalogue.png',fullPage:true});
 if(errors.length)throw Error(JSON.stringify(errors));await browser.close();console.log('Captures UI réelle de la recette isolée : OK');
})().catch(e=>{console.error(e);process.exit(1)});
