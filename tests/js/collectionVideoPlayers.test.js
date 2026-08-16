import assert from 'node:assert/strict';
import test from 'node:test';

import { isAllowedEmbedUrl } from '../../src/js/components/collectionVideoPlayers.js';

test('allows only HTTPS embeds from the supported privacy-preserving providers', () => {
  assert.equal(
    isAllowedEmbedUrl('https://www.youtube-nocookie.com/embed/abcdefghijk'),
    true,
  );
  assert.equal(
    isAllowedEmbedUrl('https://player.vimeo.com/video/123456789'),
    true,
  );
});

test('rejects insecure, untrusted, and non-HTTP URL schemes', () => {
  const rejectedUrls = [
    'http://www.youtube-nocookie.com/embed/abcdefghijk',
    'https://www.youtube.com/embed/abcdefghijk',
    'https://youtube-nocookie.com/embed/abcdefghijk',
    'https://player.vimeo.com.evil.example/video/123456789',
    'https://evil.example/video/123456789',
    'javascript:alert(1)',
    'data:text/html,<script>alert(1)</script>',
  ];

  for (const url of rejectedUrls) {
    assert.equal(isAllowedEmbedUrl(url), false, `URL should be rejected: ${url}`);
  }
});

test('resolves relative values against the supplied base URL before validating the host', () => {
  assert.equal(
    isAllowedEmbedUrl('/video/123', 'https://player.vimeo.com/'),
    true,
  );
  assert.equal(
    isAllowedEmbedUrl('/video/123', 'https://evil.example/'),
    false,
  );
});
