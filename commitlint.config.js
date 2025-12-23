// Conventional Commits configuration
// See: https://www.conventionalcommits.org/
module.exports = {
  extends: ['@commitlint/config-conventional'],
  rules: {
    // Enforce these commit types
    'type-enum': [
      2,
      'always',
      [
        'feat',     // New feature
        'fix',      // Bug fix
        'docs',     // Documentation only
        'style',    // Formatting, no code change
        'refactor', // Code change, no new feature or fix
        'perf',     // Performance improvement
        'test',     // Adding/fixing tests
        'chore',    // Maintenance, deps, config
        'security', // Security fix
        'revert',   // Revert previous commit
      ],
    ],
    // Allow longer subject lines for descriptive messages
    'subject-max-length': [1, 'always', 100],
    // Enforce lowercase type
    'type-case': [2, 'always', 'lower-case'],
  },
};
