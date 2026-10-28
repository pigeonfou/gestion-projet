const assert = require('node:assert/strict');
const {nextVersion} = require('./reserve-version.cjs');
assert.equal(nextVersion('1.0.2', []), '1.0.3');
assert.equal(nextVersion('1.0.2', ['oddworks-deb-v1.0.3']), '1.0.4');
assert.equal(nextVersion('1.0.2', ['v1.0.9','oddworks-deb-v1.0.10']), '1.0.11');
assert.equal(nextVersion('1.0.2', ['oddworks-deb-v1.1.0','oddworks-deb-v1.0.99']), '1.1.1');
assert.equal(nextVersion('1.0.2', ['unrelated','release-dev']), '1.0.3');
assert.throws(()=>nextVersion('wrong', []));
console.log('OK : historique 1.0.2, versions réservées, ordre numérique, branches majeures et tags ignorés');
