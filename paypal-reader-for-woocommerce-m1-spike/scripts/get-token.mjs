import { fetchAccessToken } from '../src/zettle-oauth.mjs';
import { writeRedactedJson } from '../src/evidence.mjs';

const result = await fetchAccessToken();
await writeRedactedJson('evidence/m1/token-response.json', result);
console.log(JSON.stringify({ expires_in: result.expires_in }, null, 2));
