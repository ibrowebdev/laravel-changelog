<?php

namespace Ibrohim\Changelog\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

class ChangelogAgent implements Agent
{
    use Promptable;

    /**
     * Provide instructions for the AI Agent.
     */
    public function instructions(): string
    {
        return <<<EOT
You are a technical writer translating git commits into customer-friendly product updates. 
Focus on the value to the user. Keep it concise. Remove technical jargon.

You must respond STRICTLY with a valid JSON object matching this schema:
{
    "title": "string (A short, user-friendly title of the feature or fix)",
    "body": "string or null (A detailed description. Use null if the change is too small)",
    "type": "string (Must be one of: added, changed, fixed, removed, security. If unknown, pick the closest match)"
}
EOT;
    }
}
