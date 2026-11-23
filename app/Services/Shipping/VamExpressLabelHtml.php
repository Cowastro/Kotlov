<?php

namespace App\Services\Shipping;

use DOMElement;
use DOMNode;

class VamExpressLabelHtml
{
    public function extract(string $html, string $expectedId): string
    {
        $xpath = (new VamExpressHtml)->xpath($html);
        $container = $xpath->query('//*[@id="thisPrint"]');
        if ($container->length !== 1) {
            throw new VamExpressException('Перевозчик не вернул этикетку. Обновите заявку и попробуйте снова.');
        }
        $ids = $xpath->query('.//span[contains(@id,"_lblSecureID")]', $container->item(0));
        if ($ids->length < 1 || $ids->length > 100) {
            throw new VamExpressException('Не удалось проверить ID на этикетке ВАМ Экспресс.');
        }
        foreach ($ids as $id) {
            if (strtolower(trim($id->textContent)) !== strtolower($expectedId)) {
                throw new VamExpressException('ID на этикетке не совпадает с выбранной заявкой. Печать остановлена.');
            }
        }
        $result = '';
        foreach ($container->item(0)->childNodes as $node) {
            $result .= $this->clean($node);
        }

        return $result;
    }

    private function clean(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return htmlspecialchars($node->textContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if (! $node instanceof DOMElement) {
            return '';
        }
        $tag = strtolower($node->tagName);
        if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta'], true)) {
            return '';
        }
        if (! in_array($tag, ['div', 'span', 'b', 'strong', 'u', 'br'], true)) {
            throw new VamExpressException('Макет этикетки ВАМ Экспресс изменился. Печать остановлена для проверки.');
        }
        if ($tag === 'br') {
            return '<br>';
        }
        $allowed = ['text-align', 'font-family', 'font-size', 'font-weight', 'line-height', 'width', 'height', 'max-width',
            'padding', 'padding-left', 'padding-right', 'padding-top', 'padding-bottom', 'margin', 'margin-top', 'margin-bottom',
            'border-bottom', 'border-left', 'float', 'vertical-align', 'overflow', 'page-break-after', 'page-break-inside'];
        $styles = [];
        foreach (explode(';', $node->getAttribute('style')) as $declaration) {
            $pair = explode(':', $declaration, 2);
            if (count($pair) !== 2) {
                continue;
            }
            [$property, $value] = array_map('trim', $pair);
            if (in_array(strtolower($property), $allowed, true) && preg_match('/^[a-zA-Z0-9#.,% !-]+$/D', $value)) {
                $styles[] = strtolower($property).':'.$value;
            }
        }
        $content = '';
        foreach ($node->childNodes as $child) {
            $content .= $this->clean($child);
        }

        return '<'.$tag.($styles ? ' style="'.htmlspecialchars(implode(';', $styles), ENT_QUOTES).'"' : '').'>'.$content.'</'.$tag.'>';
    }
}
