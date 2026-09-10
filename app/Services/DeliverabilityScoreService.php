<?php

namespace App\Services;

class DeliverabilityScoreService
{
    /**
     * Inspect DNS records of sender domain for SPF, DKIM, DMARC & MX
     */
    public function checkDomainDns(string $emailOrDomain): array
    {
        $domain = $emailOrDomain;
        if (str_contains($emailOrDomain, '@')) {
            $parts = explode('@', $emailOrDomain);
            $domain = end($parts);
        }
        $domain = trim(strtolower($domain));

        $results = [
            'domain' => $domain,
            'mx' => ['status' => false, 'records' => [], 'message' => 'No MX records found'],
            'spf' => ['status' => false, 'record' => null, 'message' => 'Missing SPF (v=spf1) record'],
            'dmarc' => ['status' => false, 'record' => null, 'message' => 'Missing DMARC (v=DMARC1) record'],
            'dkim' => ['status' => 'info', 'message' => 'DKIM selector check requires specific key selector (e.g. s1, default, google)'],
            'overall_score' => 0,
        ];

        if (empty($domain) || ! str_contains($domain, '.')) {
            return $results;
        }

        // 1. Check MX Records
        $mxRecords = @dns_get_record($domain, DNS_MX);
        if (! empty($mxRecords)) {
            $results['mx']['status'] = true;
            $results['mx']['records'] = array_column($mxRecords, 'target');
            $results['mx']['message'] = 'Valid MX routing found ('.count($mxRecords).' records)';
            $results['overall_score'] += 30;
        }

        // 2. Check SPF (TXT records on root domain)
        $txtRecords = @dns_get_record($domain, DNS_TXT);
        if (! empty($txtRecords)) {
            foreach ($txtRecords as $txt) {
                $entry = $txt['txt'] ?? '';
                if (str_starts_with(strtolower($entry), 'v=spf1')) {
                    $results['spf']['status'] = true;
                    $results['spf']['record'] = $entry;
                    $results['spf']['message'] = 'SPF record configured: '.$entry;
                    $results['overall_score'] += 35;
                    break;
                }
            }
        }

        // 3. Check DMARC (TXT record on _dmarc.domain)
        $dmarcRecords = @dns_get_record('_dmarc.'.$domain, DNS_TXT);
        if (! empty($dmarcRecords)) {
            foreach ($dmarcRecords as $txt) {
                $entry = $txt['txt'] ?? '';
                if (str_starts_with(strtolower($entry), 'v=dmarc1')) {
                    $results['dmarc']['status'] = true;
                    $results['dmarc']['record'] = $entry;
                    $results['dmarc']['message'] = 'DMARC policy active: '.$entry;
                    $results['overall_score'] += 35;
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * Scan email content for spam trigger words and analyze HTML/text ratio
     */
    public function analyzeSpamScore(string $subject, string $htmlBody, string $textBody = ''): array
    {
        $spamKeywords = [
            '100% free', 'act now', 'apply now', 'become your own boss', 'buy direct',
            'cash bonus', 'cheap', 'click below', 'click here', 'compare rates',
            'congratulations', 'credit card offers', 'cures baldness', 'dear friend',
            'direct email', 'direct marketing', 'double your income', 'earn extra cash',
            'eliminate debt', 'exclusive deal', 'expect to earn', 'extra income',
            'fast cash', 'financial freedom', 'free consultation', 'free gift',
            'free info', 'free membership', 'free preview', 'free sample',
            'free trial', 'full refund', 'get out of debt', 'get paid',
            'giveaway', 'guaranteed', 'increase sales', 'instant earnings',
            'limited time offer', 'lose weight fast', 'make money', 'million dollars',
            'no catch', 'no cost', 'no credit check', 'no experience',
            'no fees', 'no gimmick', 'no hidden costs', 'no obligation',
            'no purchase necessary', 'no risk', 'no strings attached',
            'once in a lifetime', 'one time offer', 'online marketing',
            'open immediately', 'opportunity', 'order now', 'passwords',
            'pennies a day', 'pure profit', 'refinance', 'risk free',
            'save big', 'save money', 'special promotion', 'urgent',
            'valuable info', 'viagra', 'vicodin', 'warranty',
            'weight loss', 'while supplies last', 'win $$$', 'winner',
            'winning', 'work from home', 'you have been selected',
        ];

        $matchedWords = [];
        $combinedText = strtolower($subject.' '.strip_tags($htmlBody).' '.$textBody);

        foreach ($spamKeywords as $keyword) {
            if (str_contains($combinedText, $keyword)) {
                $matchedWords[] = $keyword;
            }
        }

        // Check All-Caps in Subject
        $capsInSubject = false;
        if (strlen($subject) > 8 && strtoupper($subject) === $subject && preg_match('/[A-Z]/', $subject)) {
            $capsInSubject = true;
        }

        // Check Exclamations
        $excessiveExclamation = (substr_count($subject, '!') > 2) || (substr_count($combinedText, '!!!') > 0);

        // Check Text-to-HTML Ratio
        $htmlLength = strlen($htmlBody);
        $plainTextLength = strlen(strip_tags($htmlBody));
        $textRatio = $htmlLength > 0 ? round(($plainTextLength / $htmlLength) * 100, 1) : 100;

        $riskScore = 0; // 0 = Clean, 100 = High Spam Risk
        $riskScore += min(50, count($matchedWords) * 10);
        if ($capsInSubject) {
            $riskScore += 20;
        }
        if ($excessiveExclamation) {
            $riskScore += 15;
        }
        if ($textRatio < 15 && $htmlLength > 200) {
            $riskScore += 15;
        }

        $riskScore = min(100, $riskScore);

        $grade = 'Excellent (Inbox Guaranteed)';
        $color = 'emerald';
        if ($riskScore > 40) {
            $grade = 'Medium Risk (May trigger Promotions/Spam tab)';
            $color = 'amber';
        }
        if ($riskScore > 70) {
            $grade = 'High Risk (Likely Spam Folder)';
            $color = 'rose';
        }

        return [
            'score' => $riskScore,
            'grade' => $grade,
            'color' => $color,
            'matched_keywords' => array_unique($matchedWords),
            'caps_in_subject' => $capsInSubject,
            'excessive_exclamation' => $excessiveExclamation,
            'text_ratio' => $textRatio,
        ];
    }
}
