#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Auditoria Editorial V3 (Python) - TV Sumare Enterprise
Replicacao fiel das regras de tools/editorial_sanitize.php
"""
import json
import sys
import os
import unicodedata
from datetime import datetime, timezone

# --- Regras da Politica Editorial V3 ---
ALLOWED_CITIES = {
    'sumare': 'Sumaré',
    'hortolandia': 'Hortolândia',
    'paulinia': 'Paulínia',
    'nova odessa': 'Nova Odessa',
    'americana': 'Americana',
    'campinas': 'Campinas'
}

OUTSIDE_CITIES = [
    'mossoró', 'mossoro', 'caraguatatuba', 'santos', 'são vicente', 'sao vicente',
    'praia grande', 'guarujá', 'guaruja', 'ubatuba', 'são sebastião', 'sao sebastiao',
    'ilhabela', 'sorocaba', 'ribeirão preto', 'ribeirao preto', 'são josé dos campos',
    'sao jose dos campos', 'taubaté', 'taubate', 'piracicaba', 'limeira', 'jundiaí',
    'jundiai', 'indaiatuba', 'atibaia', 'cosmópolis', 'cosmopolis', 'monte mor',
    'vinhedo', 'valinhos', 'itapira', 'mogi mirim', 'mogi guaçu', 'mogi guacu',
    'bauru', 'marília', 'marilia', 'presidente prudente'
]

def clean_text(s: str) -> str:
    if not isinstance(s, str):
        return ''
    s = unicodedata.normalize('NFKC', s).lower()
    return ' '.join(s.split()).strip()

def content_text(item: dict) -> str:
    parts = [
        item.get('title', ''), item.get('subtitle', ''), item.get('summary', ''),
        item.get('body', ''), item.get('source', ''), item.get('source_url', ''),
        item.get('city', ''), item.get('category', '')
    ]
    return clean_text(' '.join([str(p) for p in parts if p]))

def external_municipal_source(item: dict) -> str:
    combined = content_text(item)
    for city in OUTSIDE_CITIES:
        if city in combined:
            return city
    return ''

def region_city_detect(item: dict) -> str:
    txt = content_text(item)
    for city, canon in ALLOWED_CITIES.items():
        if city in txt:
            return canon
    return ''

# --- Saneador ---
def read_json(path):
    if not os.path.isfile(path):
        return []
    with open(path, 'r', encoding='utf-8') as f:
        return json.load(f)

def date_ts(item):
    for k in ['published_at', 'created_at', 'date', 'datetime', 'timestamp']:
        v = item.get(k, '')
        if isinstance(v, str) and v.strip():
            try:
                dt = datetime.fromisoformat(v.replace('Z', '+00:00'))
                return dt.timestamp()
            except Exception:
                continue
    return None

def label(item):
    return item.get('title') or item.get('titulo') or item.get('name') or 'sem título'

def main():
    base = os.path.dirname(os.path.abspath(__file__))
    news_path = os.path.join(base, '..', 'data', 'noticias.json')
    breaking_path = os.path.join(base, '..', 'data', 'ultimahora.json')
    news = read_json(news_path)
    breaking = read_json(breaking_path)

    keep_news, keep_breaking, quarantine, review = [], [], [], []

    for item in news:
        outside = external_municipal_source(item)
        if outside:
            quarantine.append({'dataset': 'noticias', 'reason': 'external_municipal_source:'+outside, 'item': item})
            continue
        if region_city_detect(item) == '' and str(item.get('city', '')).strip() != '':
            review.append({'dataset': 'noticias', 'reason': 'city_without_content_evidence', 'title': label(item), 'city': item.get('city', '')})
        keep_news.append(item)

    cutoff = datetime.now(timezone.utc).timestamp() - (7*86400)
    for item in breaking:
        ts = date_ts(item)
        if ts is not None and ts < cutoff:
            quarantine.append({'dataset': 'ultimahora', 'reason': 'breaking_older_than_7_days', 'item': item})
            continue
        if ts is None:
            review.append({'dataset': 'ultimahora', 'reason': 'breaking_without_parseable_date', 'title': label(item)})
        keep_breaking.append(item)

    summary = {
        'mode': 'dry-run',
        'news_total': len(news), 'news_keep': len(keep_news),
        'breaking_total': len(breaking), 'breaking_keep': len(keep_breaking),
        'quarantine': len(quarantine), 'review': len(review)
    }
    print(json.dumps(summary, ensure_ascii=False))
    for q in quarantine:
        print(f"QUARANTINE {q['dataset']} {q['reason']} :: {label(q['item'])}")
    for r in review[:50]:
        print(f"REVIEW {r['dataset']} {r['reason']} :: {r['title']}")
    print('EDITORIAL_SANITIZE=DRY_RUN')

if __name__ == '__main__':
    main()
