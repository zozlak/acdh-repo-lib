<?php

/*
 * The MIT License
 *
 * Copyright 2019 Austrian Centre for Digital Humanities.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace acdhOeaw\acdhRepoLib;

/**
 * Description of SearchTerm
 *
 * @author zozlak
 */
class SearchTerm {

    const TYPE_RELATION = 'relation';
    const TYPE_NUMBER   = 'number';
    const TYPE_DATE     = 'date';
    const TYPE_DATETIME = 'datetime';
    const TYPE_STRING   = 'string';

    public $property;
    public $operator;
    public $value;
    public $type;
    public $language;

    public function __construct(?string $property = null, $value = null,
                                string $operator = '=', ?string $type = null,
                                ?string $language = null) {
        $this->property = $property;
        $this->operator = $operator;
        $this->value    = $value;
        $this->type     = $type;
        $this->language = $language;
    }

    public function getFormData(): string {
        $terms = [];
        foreach ($this as $k => $v) {
            if ($v !== null) {
                $terms[$k . '[]'] = (string) $v;
            }
        }
        return http_build_query($terms);
    }

}
