-- Подкатегории Трубы и фитинги (parent=193)
INSERT INTO categories (id, parent_id, name, slug, is_active, sort_order, type, created_at, updated_at) VALUES
(329, 193, 'Полипропиленовые трубы',             'polipropilenovye-truby',                1, 10, 'catalog', NOW(), NOW()),
(330, 193, 'Полиэтиленовые трубы',               'polietilenovye-truby',                  1, 20, 'catalog', NOW(), NOW()),
(331, 193, 'Трубы из сшитого полиэтилена',       'truby-iz-sshitogo-polietilena',         1, 30, 'catalog', NOW(), NOW()),
(332, 193, 'Металлопластиковые трубы',            'metalloplastikovye-truby',              1, 40, 'catalog', NOW(), NOW()),
(333, 193, 'Канализационные трубы',               'kanalizatsionnye-truby',                1, 50, 'catalog', NOW(), NOW()),
(334, 193, 'Гофрированные трубы',                 'gofrirovanye-truby',                    1, 60, 'catalog', NOW(), NOW()),
(335, 193, 'Трубы для теплого водяного пола',    'truby-dlya-teplogo-vodyanogo-pola',     1, 70, 'catalog', NOW(), NOW()),
(336, 193, 'Напорные трубы из полиэтилена',      'napornye-truby-iz-polietilena',         1, 80, 'catalog', NOW(), NOW()),
(337, 193, 'Трубы защитные',                      'truby-zashchitnye',                     1, 90, 'catalog', NOW(), NOW()),
(338, 193, 'Фитинги для металлопластиковых труб','fitingi-dlya-metalloplastikovykh-trub', 1,100, 'catalog', NOW(), NOW()),
(339, 193, 'Шаровые краны',                       'sharovye-krany',                        1,110, 'catalog', NOW(), NOW()),
(340, 193, 'Резьбовые фитинги',                   'rezbovye-fitingi',                      1,120, 'catalog', NOW(), NOW())
ON DUPLICATE KEY UPDATE parent_id=VALUES(parent_id), name=VALUES(name), slug=VALUES(slug), is_active=1;

-- Подкатегории Теплый пол (parent=109)
INSERT INTO categories (id, parent_id, name, slug, is_active, sort_order, type, created_at, updated_at) VALUES
(341, 109, 'Нагревательные маты',                 'nagrevatelnie-maty',                    1, 10, 'catalog', NOW(), NOW()),
(342, 109, 'Нагревательные кабели',               'nagrevatelnie-kabeli',                  1, 20, 'catalog', NOW(), NOW()),
(343, 109, 'ИК Пленочный пол',                    'ik-plenochny-pol',                      1, 30, 'catalog', NOW(), NOW()),
(344, 109, 'Подложка под теплый пол',             'podlozhka-pod-teplyy-pol',              1, 40, 'catalog', NOW(), NOW()),
(345, 109, 'Теплый пол под ламинат',              'teplyy-pol-pod-laminat',                1, 50, 'catalog', NOW(), NOW()),
(346, 109, 'Теплый пол под плитку',               'teplyy-pol-pod-plitku',                 1, 60, 'catalog', NOW(), NOW()),
(347, 109, 'Терморегуляторы для теплого пола',   'termoregulyatory-dlya-teplogo-pola',    1, 70, 'catalog', NOW(), NOW()),
(348, 109, 'Комплектующие для теплого пола',      'komplektuyushchie-dlya-teplogo-pola',   1, 80, 'catalog', NOW(), NOW()),
(357, 109, 'Саморегулирующиеся кабели',           'samoreguliruyushchiesya-kabeli',        1, 90, 'catalog', NOW(), NOW())
ON DUPLICATE KEY UPDATE parent_id=VALUES(parent_id), name=VALUES(name), slug=VALUES(slug), is_active=1;

-- Подкатегории Климат (parent=304) — новые
INSERT INTO categories (id, parent_id, name, slug, is_active, sort_order, type, created_at, updated_at) VALUES
(349, 304, 'Вентиляторы',          'ventilyatory',         1, 20, 'catalog', NOW(), NOW()),
(350, 304, 'Масляные обогреватели','maslyanye-obrevateli', 1, 30, 'catalog', NOW(), NOW()),
(351, 304, 'Инфракрасные обогреватели','infrakrasnye-obrevateli',1, 40, 'catalog', NOW(), NOW()),
(352, 304, 'Тепловентиляторы',     'teploventilyatory',    1, 50, 'catalog', NOW(), NOW()),
(353, 304, 'Тепловые завесы',      'teplovye-zavesy',      1, 60, 'catalog', NOW(), NOW()),
(354, 304, 'Увлажнители воздуха',  'uvlazhniteli-vozdukha',1, 70, 'catalog', NOW(), NOW()),
(355, 304, 'Мойки воздуха',        'mojki-vozdukha',       1, 80, 'catalog', NOW(), NOW())
ON DUPLICATE KEY UPDATE parent_id=VALUES(parent_id), name=VALUES(name), slug=VALUES(slug), is_active=1;

-- Подкатегории Насосы (parent=303) — новые
INSERT INTO categories (id, parent_id, name, slug, is_active, sort_order, type, created_at, updated_at) VALUES
(325, 303, 'Насосы повышения давления', 'nasosy-povysheniya-davleniya', 1, 10, 'catalog', NOW(), NOW()),
(326, 303, 'Насосы для колодца',        'nasosy-dlya-kolodtsa',         1, 20, 'catalog', NOW(), NOW()),
(327, 303, 'Фекальные насосы',          'fekalnye-nasosy',              1, 30, 'catalog', NOW(), NOW()),
(328, 303, 'Канализационные насосы',    'kanalizatsionnye-nasosy',      1, 40, 'catalog', NOW(), NOW())
ON DUPLICATE KEY UPDATE parent_id=VALUES(parent_id), name=VALUES(name), slug=VALUES(slug), is_active=1;

-- Биокамины (parent=51 Камины)
INSERT INTO categories (id, parent_id, name, slug, is_active, sort_order, type, created_at, updated_at) VALUES
(356, 51, 'Биокамины', 'biokaminy', 1, 60, 'catalog', NOW(), NOW())
ON DUPLICATE KEY UPDATE parent_id=51, name='Биокамины', slug='biokaminy', is_active=1;

-- Система защиты от протечек (parent=195 Комплектующие)
INSERT INTO categories (id, parent_id, name, slug, is_active, sort_order, type, created_at, updated_at) VALUES
(358, 195, 'Система защиты от протечек', 'sistema-zashchity-ot-protechek', 1, 200, 'catalog', NOW(), NOW())
ON DUPLICATE KEY UPDATE parent_id=195, name='Система защиты от протечек', slug='sistema-zashchity-ot-protechek', is_active=1;
