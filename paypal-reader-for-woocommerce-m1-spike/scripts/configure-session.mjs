import { readRequiredEnv } from '../src/env.mjs';
import { fetchAccessToken } from '../src/zettle-oauth.mjs';
import { configureSession } from '../src/reader-connect-rest.mjs';
import { writeRedactedJson } from '../src/evidence.mjs';

const { ZETTLE_LINK_ID, ZETTLE_CHANNEL_ID } = readRequiredEnv([
  'ZETTLE_LINK_ID',
  'ZETTLE_CHANNEL_ID',
]);
const token = await fetchAccessToken();
const session = await configureSession({
  token: token.access_token,
  linkId: ZETTLE_LINK_ID,
  channelId: ZETTLE_CHANNEL_ID,
});

await writeRedactedJson('evidence/m1/session-sample.json', session);
console.log(JSON.stringify(session, null, 2));
