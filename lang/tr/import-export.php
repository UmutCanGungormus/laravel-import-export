<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Import / Export — Turkish
    |--------------------------------------------------------------------------
    */

    'status' => [
        'pending' => 'Bekliyor',
        'mapping' => 'Eşleştirme',
        'processing' => 'İşleniyor',
        'completed' => 'Tamamlandı',
        'completed_with_errors' => 'Hatalarla tamamlandı',
        'failed' => 'Başarısız',
        'cancelled' => 'İptal Edildi',
    ],

    'session' => [
        'created' => 'İçeri aktarma oturumu oluşturuldu.',
        'updated' => 'İçeri aktarma oturumu güncellendi.',
        'cancelled' => 'İçeri aktarma oturumu iptal edildi.',
        'not_found' => 'İçeri aktarma oturumu bulunamadı.',
        'already_processing' => 'Bu içeri aktarma zaten işleniyor.',
        'invalid_status_transition' => 'İstenen durum geçişine izin verilmiyor.',
        'invalid_model' => 'Seçilen model kayıtlı değil.',
        'no_processor_registered' => 'Seçilen model için tanımlı bir işleyici yok.',
    ],

    'mapping' => [
        'updated' => 'Eşleştirme güncellendi.',
        'required_missing' => 'Bir veya daha fazla zorunlu kolon eşleştirilmedi: :fields',
        'unknown_target_field' => ':model için tanımsız hedef alan ":field".',
    ],

    'template' => [
        'created' => 'Şablon oluşturuldu.',
        'updated' => 'Şablon güncellendi.',
        'deleted' => 'Şablon silindi.',
        'applied' => 'Şablon uygulandı.',
        'set_default' => 'Şablon varsayılan olarak atandı.',
        'limit_reached' => 'Maksimum şablon sayısına ulaşıldı (:max).',
        'not_found' => 'Şablon bulunamadı.',
    ],

    'export' => [
        'yes' => 'Evet',
        'no' => 'Hayır',
        'started' => 'Dışa aktarma başlatıldı.',
        'completed' => 'Dışa aktarma tamamlandı.',
    ],

    'errors' => [
        'file_not_readable' => 'Yüklenen dosya okunamıyor.',
        'no_headers_detected' => 'Dosyada başlık satırı bulunamadı.',
        'empty_file' => 'Yüklenen dosya boş.',
        'row_validation_failed' => ':row. satır doğrulamadan geçemedi.',
    ],

    /*
    | Host uygulamalar kendi alan etiketlerini buraya ekler.
    */
    'fields' => [
        'common' => [
            'id' => 'ID',
            'created_at' => 'Oluşturulma Tarihi',
            'updated_at' => 'Güncellenme Tarihi',
        ],
    ],
];
