import { readRequiredEnv } from '../src/env.mjs';
import { fetchAccessToken } from '../src/zettle-oauth.mjs';
import { deleteLink } from '../src/reader-connect-rest.mjs';

const { ZETTLE_LINK_ID } = readRequiredEnv(['ZETTLE_LINK_ID']);
const token = await fetchAccessToken();
await deleteLink({ token: token.access_token, linkId: ZETTLE_LINK_ID });

console.log(JSON.stringify({ deleted: ZETTLE_LINK_ID }, null, 2));
