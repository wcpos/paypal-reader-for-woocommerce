import { readRequiredEnv } from '../src/env.mjs';
import { fetchAccessToken } from '../src/zettle-oauth.mjs';
import { claimLinkOffer } from '../src/reader-connect-rest.mjs';
import { writeRedactedJson } from '../src/evidence.mjs';

const { ZETTLE_PAIRING_CODE, ZETTLE_DEVICE_NAME } = readRequiredEnv([
  'ZETTLE_PAIRING_CODE',
  'ZETTLE_DEVICE_NAME',
]);
const token = await fetchAccessToken();
const link = await claimLinkOffer({
  token: token.access_token,
  code: ZETTLE_PAIRING_CODE,
  deviceName: ZETTLE_DEVICE_NAME,
});

await writeRedactedJson('evidence/m1/claim-link-response.json', link);
console.log(JSON.stringify({ linkId: link.id }, null, 2));
