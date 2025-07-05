# Providers Note

## SMS.ir

- This provider does not offer a dedicated API for sending OTP messages. Use the pattern-based method instead.
- This provider doesn't support sending pattern to multiple phones at once.
- Pattern variables must be passed as key-value pairs.

## Meli Payamak

- This provider does not offer a dedicated API for sending OTP messages. Use the pattern-based method instead.
- This provider doesn't support sending pattern to multiple phones at once.
- Pattern variables must be passed in **order only** — key-value pairs are not accepted. The package will discard the keys and send the values in the order they were provided.

## Payam Resan

- This provider does not offer a dedicated API for sending OTP messages. Use the pattern-based method instead.
- This provider doesn't support sending pattern to multiple phones at once.
- Pattern variables must be passed as key-value pairs.
- This provider accepts exactly 3 items as pattern variables.

## Kavenegar

- This provider does not offer a dedicated API for sending OTP messages. Use the pattern-based method instead.
- This provider doesn't support sending pattern to multiple phones at once.
- Pattern variables must be passed as key-value pairs.

## Faraz SMS

- This provider does not offer a dedicated API for sending OTP messages. Use the pattern-based method instead.
- Pattern variables must be passed as key-value pairs.

## Raygan SMS

- This provider supports a dedicated web service for sending OTP messages.
- For patterns, you need an access token; for other types, you need a username and password.
- Pattern variables must be passed as key-value pairs.
- You can send one pattern to multiple phone numbers in a single API call.
