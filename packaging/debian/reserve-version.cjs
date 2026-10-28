const fs = require('node:fs');
function parseVersion(value) {
  const m = /^(?:oddworks-deb-v|v)?(\d+)\.(\d+)\.(\d+)$/.exec(value.trim());
  if (!m) return null;
  const n = m.slice(1).map(Number);
  if (!n.every(Number.isSafeInteger)) throw new Error('Version hors limites');
  return n;
}
function nextVersion(baseline, tags) {
  let highest = parseVersion(baseline);
  if (!highest) throw new Error('Version historique invalide');
  for (const tag of tags) {
    const v = parseVersion(tag);
    if (v && (v[0] > highest[0] || v[0] === highest[0] && (v[1] > highest[1] || v[1] === highest[1] && v[2] > highest[2]))) highest = v;
  }
  if (highest[2] === Number.MAX_SAFE_INTEGER) throw new Error('Version hors limites');
  return [highest[0], highest[1], highest[2] + 1].join('.');
}
async function reserve({github, context, core}) {
  const baseline = fs.readFileSync('packaging/debian/last-legacy-version', 'utf8').trim();
  for (let attempt = 0; attempt < 5; attempt++) {
    const tags = await github.paginate(github.rest.repos.listTags, {...context.repo, per_page:100});
    const version = nextVersion(baseline, tags.map(t=>t.name));
    const tag = 'oddworks-deb-v' + version;
    try {
      await github.rest.git.createRef({...context.repo, ref:'refs/tags/'+tag, sha:context.sha});
    } catch (e) {
      if (e.status === 422) continue; // Another run may have reserved this exact version.
      throw e;
    }
    core.exportVariable('ODDWORKS_PACKAGE_VERSION', version);
    core.exportVariable('ODDWORKS_PACKAGE_TAG', tag);
    core.setOutput('version', version);
    core.setOutput('tag', tag);
    core.info('Version réservée : '+version+' — '+context.sha);
    return;
  }
  throw new Error('Réservation de version impossible après cinq tentatives');
}
module.exports = {nextVersion, reserve};
