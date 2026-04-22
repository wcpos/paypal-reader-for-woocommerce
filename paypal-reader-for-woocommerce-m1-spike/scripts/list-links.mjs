import { fetchAccessToken } from '../src/zettle-oauth.mjs';
import { getLinks } from '../src/reader-connect-rest.mjs';
import { writeRedactedJson } from '../src/evidence.mjs';

const token = await fetchAccessToken();
const links = await getLinks({ token: token.access_token });

await writeRedactedJson('evidence/m1/links-list.json', links);
console.log(JSON.stringify(links, null, 2));
