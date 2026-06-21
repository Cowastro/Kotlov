#!/bin/bash
PHP=/opt/alt/php83/usr/bin/php
ART="$PHP artisan"
LOG=/tmp/enrich_batch2.log
SLEEP=800

run_brand() {
    local BRAND="$1"
    local BRAND_ID="$2"
    echo "" >> $LOG
    echo "============================================" >> $LOG
    echo "=== BRAND: $BRAND  $(date) ===" >> $LOG
    echo "============================================" >> $LOG

    echo "[$BRAND] Step 1: import-catalog" >> $LOG
    $ART supplier:import-catalog-teplodvor --brand="$BRAND" --apply --skip-archive --with-ai --sleep=$SLEEP >> $LOG 2>&1

    echo "[$BRAND] Cleanup: delete zero-price (discontinued)" >> $LOG
    $PHP artisan tinker --execute="
        \$deleted = DB::table('products')->where('brand_id',$BRAND_ID)->where('is_archived',0)->where('price',0)->delete();
        echo 'Deleted: '.\$deleted;
    " >> $LOG 2>&1

    echo "[$BRAND] Step 2: enrich" >> $LOG
    $ART supplier:enrich-teplodvor --brand="$BRAND" --apply --sleep=$SLEEP >> $LOG 2>&1

    echo "[$BRAND] Step 3: import-attributes" >> $LOG
    $ART supplier:import-attributes-teplodvor --brand="$BRAND" --apply >> $LOG 2>&1

    echo "[$BRAND] DONE $(date)" >> $LOG
}

echo "=== BATCH 2 START $(date) ===" > $LOG

run_brand "BAXI"       115
run_brand "Protherm"   23
run_brand "Navien"     276
run_brand "Viessmann"  171
run_brand "Grundfos"   30
run_brand "Rifar"      318

echo "" >> $LOG
echo "=== BATCH 2 ALL DONE $(date) ===" >> $LOG
