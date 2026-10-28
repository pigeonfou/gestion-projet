const fs = require('node:fs');
module.exports = async ({github, context, core}) => {
  const version = process.env.ODDWORKS_PACKAGE_VERSION;
  const tag = process.env.ODDWORKS_PACKAGE_TAG;
  if (!/^\d+\.\d+\.\d+$/.test(version) || tag !== 'oddworks-deb-v'+version) throw new Error('Version réservée manquante');
  const filename = 'oddworks_'+version+'_all.deb';
  const provenance = JSON.parse(fs.readFileSync('dist/PROVENANCE.json', 'utf8'));
  if (provenance.commit !== context.sha || provenance.tag !== tag) throw new Error('Provenance incohérente');
  const body = 'Construit et testé par GitHub Actions sur Ubuntu 24.04. **Recette sur majiflow à effectuer ; non validé en production.**\n\nCommit : `'+context.sha+'`\n\n[Exécution officielle]('+provenance.run_url+')\n\nLe fichier Debian ci-dessous est identique à celui de l’artefact `oddworks-deb-ubuntu-24.04`. Les empreintes et la provenance sont jointes.';
  const {data:release} = await github.rest.repos.createRelease({...context.repo, tag_name:tag, name:'OddWorks '+version+' — recette majiflow à effectuer', body, draft:true, prerelease:true, make_latest:'false'});
  let download;
  for (const name of [filename, 'PROVENANCE.json', 'SHA256SUMS']) {
    const data = fs.readFileSync('dist/'+name);
    const {data:asset} = await github.rest.repos.uploadReleaseAsset({...context.repo, release_id:release.id, name, data, headers:{'content-type':'application/octet-stream','content-length':data.length}});
    if (name === filename) download = asset.browser_download_url;
  }
  const {data:published} = await github.rest.repos.updateRelease({...context.repo, release_id:release.id, draft:false, prerelease:true, make_latest:'false'});
  await core.summary.addHeading('OddWorks '+version).addLink('Télécharger directement '+filename, download).addRaw('\n\nCommit : `'+context.sha+'`\n\nRecette majiflow à effectuer.\n\n').addLink('Provenance et empreintes', published.html_url).write();
  core.info('Téléchargement direct : '+download);
};
