# Google customer authentication API

This document describes the customer API/mobile integration exposed by the SocialAuth package. It does not cover administrator SSO; Google authentication is customer-only.

## Configuration

An administrator configures Google authentication under **Admin → System → Authentication**:

- enable the feature and API/mobile channel;
- select the active **Test** or **Live** profile;
- enable or disable new customer registration;
- add every Google OAuth client ID that can appear as the ID token's `aud` claim for this API, including the relevant Android/iOS client IDs;
- set an ID-token maximum age (1–60 minutes).

The client IDs supplied by the mobile team and the native client implementation are not present in this repository. Coordinate those IDs with the administrator before enabling a profile. Never embed a web OAuth client secret in a mobile application. The Google ID token must carry the exact nonce returned by this API.

All endpoints use the existing v1 JSON envelope. Success responses have `success`, `message`, `data`, and `meta`; failures include `success: false`, `message`, `error_code`, and, for validation failures, `errors`.

## Sign in

1. Optional client capability check:

   `GET /api/v1/auth/google/config`

   A successful response's `data.enabled` indicates that Google API sign-in is currently available. `registration_enabled` indicates whether a previously unlinked Google identity can create a new customer. The response contains no credentials or client secrets.

2. Request a one-use nonce:

   `POST /api/v1/auth/google/nonce`

   ```json
   {}
   ```

   The response contains `data.nonce` and `data.expires_in` (currently 300 seconds). Keep the nonce in memory only. Request a Google ID token using this exact nonce.

3. Exchange the ID token:

   `POST /api/v1/auth/google/login`

   ```json
   {
     "id_token": "<Google ID token>",
     "nonce": "<nonce from step 2>",
     "device_name": "ios-app"
   }
   ```

   `device_name` is optional (maximum 255 characters). A normal success response returns a Sanctum bearer token, `token_type`, `expires_in`, and the user resource. The bearer token expires according to the existing `bulbula-api.token.expiration` setting.

   If the customer has two-factor authentication enabled, the endpoint instead returns HTTP 202 with `data.two_factor_required: true`, a short-lived `challenge_token`, and `expires_in`. Submit the customer's TOTP or recovery code to `POST /api/v1/auth/google/two-factor`:

   ```json
   {
     "challenge_token": "<challenge token>",
     "code": "<TOTP or recovery code>"
   }
   ```

   The response then returns the bearer token. The challenge allows at most five attempts and expires after five minutes.

## Link and unlink Google for an existing customer

Account linking is explicit; a matching email address never attaches a Google identity to an existing account.

1. Authenticate with the customer's existing Sanctum bearer token. Reconfirm their current local password and request a link nonce:

   `POST /api/v1/auth/google/link/nonce`

   ```json
   { "current_password": "<current local password>" }
   ```

2. Request a Google ID token using the returned nonce, then submit:

   `POST /api/v1/auth/google/link`

   ```json
   {
     "id_token": "<Google ID token containing the nonce>",
     "nonce": "<link nonce>"
   }
   ```

   This endpoint is authenticated with `Authorization: Bearer <Sanctum token>` and only links the Google subject to that authenticated customer. A Google identity already owned by another account, or a different Google identity already linked to this account, is rejected.

3. To unlink, authenticate with the same bearer token and submit the current password:

   `DELETE /api/v1/auth/google/link`

   ```json
   { "current_password": "<current local password>" }
   ```

Google-created accounts use a random, undisclosed local password. Before disconnecting Google from such an account, the customer must establish a local password through the site's password-reset flow; the storefront Connected Accounts page explains this.

## Server-side checks

The server verifies Google's signature and issuer, expiry, exact configured audience, `sub`, token freshness, and the one-use nonce. It does not trust client-provided email or profile data. Existing accounts are never automatically linked by email. Inactive and admin users cannot sign in through these customer endpoints. A Google `email_verified` claim may set the local verification timestamp when creating a user; it is not a sign-in prerequisite. The Google flow itself adds no terms-acceptance requirement.

The login, nonce, linking, and two-factor routes are rate limited. Do not log ID tokens, bearer tokens, OAuth authorization codes, current passwords, or nonces.
