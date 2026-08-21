import json
import math
import sys

import pandas as pd


def text(value):
    if value is None or (isinstance(value, float) and math.isnan(value)):
        return ''
    return str(value).strip()


def find_column(row, needle):
    for index, value in enumerate(row):
        if needle in text(value).lower():
            return index
    return None


def main(source, destination):
    book = pd.ExcelFile(source)
    rows = []

    for sheet in book.sheet_names:
        frame = pd.read_excel(source, sheet_name=sheet, header=None)
        for header_index, header in frame.iterrows():
            article_index = find_column(header.tolist(), 'артикул')
            retail_index = find_column(header.tolist(), 'розница')
            # The MC Black sheet contains an old matrix on the left and the
            # actual import table on the right. The correct product name is
            # always the column immediately before "Артикул".
            name_index = article_index - 1 if article_index is not None else None
            if article_index is None or retail_index is None or name_index is None:
                continue

            wholesale_index = find_column(header.tolist(), 'опт')
            for _, source_row in frame.iloc[header_index + 1 :].iterrows():
                article = text(source_row.iloc[article_index])
                name = text(source_row.iloc[name_index])
                retail = text(source_row.iloc[retail_index])
                if not article or not name or not retail or retail == '-':
                    continue
                try:
                    retail = round(float(retail.replace(',', '.')), 2)
                except ValueError:
                    continue

                wholesale = text(source_row.iloc[wholesale_index]) if wholesale_index is not None else ''
                rows.append({
                    'name': name,
                    'sku': article,
                    'retail': f'{retail:.2f}',
                    'wholesale': wholesale,
                    'sheet': sheet,
                })
            break

    output = {
        'supplier': 'СанБизнесГруп',
        'brand': 'Теплов и Сухов',
        'price_date': '2026-08-10',
        'price_column': 'retail',
        'source_file': 'ТиС_ОПТ_10.08.2026_SBG .xlsx',
        'rows': rows,
    }
    with open(destination, 'w', encoding='utf-8') as file:
        json.dump(output, file, ensure_ascii=False, indent=2)
        file.write('\n')
    print(f'Extracted {len(rows)} rows from {len(book.sheet_names)} sheets.')


if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2])
