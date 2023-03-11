<?php
defined("_JEXEC") or die();

use Joomla\CMS\Factory;

class plgSystemNumbergenerator extends JPlugin
{
    // Функция обработки содержимого страницы после ее рендеринга
    public function onAfterRender()
    {
        // Получаем объект приложения Joomla
        $app = JFactory::getApplication();

        // Работаем только с фронтендом
        if ($app->isClient("administrator")) {
            return;
        }

        // Получаем содержимое страницы
        $body = JResponse::getBody();

        // Определяем регулярное выражение для поиска строк вида {number=min-max-id}
        $regex = "/{number=(\d+)-(\d+)-(\w+)}/";

        // Ищем все совпадения регулярного выражения в содержимом страницы
        preg_match_all($regex, $body, $matches);

        // Если найдены совпадения
        if ($matches) {
            // Перебираем каждое совпадение
            for ($i = 0; $i < count($matches[0]); $i++) {
                // Получаем минимальное и максимальное значение для генерации случайного числа
                $min = intval($matches[1][$i]);
                $max = intval($matches[2][$i]);
                // Получаем идентификатор числа
                $id = strval($matches[3][$i]);

                // Проверяем, есть ли число с таким идентификатором в базе данных
                if ($this->checkNumber($id)) {
                    // Если число есть в базе данных, то получаем его
                    $random_number = $this->getNumber($id);
                    // Если число пусто, то генерируем новое случайное число и сохраняем его в базе данных
                    if (empty($random_number)) {
                        $random_number = rand($min, $max);
                        $this->saveNumber($id, $random_number);
                    }
                } else {
                    // Если число не найдено в базе данных, то генерируем новое случайное число и сохраняем его в базе данных
                    $random_number = rand($min, $max);
                    $this->saveNumber($id, $random_number);
                }

                // Заменяем строку {number=min-max-id} на сгенерированное число в содержимом страницы
                $reg = "/" . $matches[0][$i] . "/";
                $random_number = sprintf("%02d", $random_number);
                $body = preg_replace($reg, $random_number, $body, 1);
            }
        }

        // Устанавливаем новое содержимое страницы
        JResponse::setBody($body);
    }

    // Функция сохранения числа в базе данных
    protected function saveNumber($id, $number)
    {
        // Получаем объект базы данных Joomla
        $db = JFactory::getDbo();

        // Создаем новый объект запроса
        $query = $db->getQuery(true);

        // Определяем имя таблицы для хранения сгенерированных чисел в базе данных
        $table = $db->quoteName("#__generated_numbers");

        // Проверяем, есть ли уже число с заданным идентификатором в базе данных
        $query
            ->select("number")
            ->from($table)
            ->where("id = " . $db->quote($id));
        $db->setQuery($query);
        $existing_number = $db->loadResult();

        // Если число уже существует, то обновляем его в базе данных
        if ($existing_number !== null) {
            $query
                ->clear()
                ->update($table)
                ->set("number = " . $db->quote($number))
                ->where("id = " . $db->quote($id));
        }
        // Иначе, вставляем новое число в базу данных
        else {
            $query
                ->clear()
                ->insert($table)
                ->columns([$db->quoteName("id"), $db->quoteName("number")])
                ->values([$db->quote($id), $db->quote($number)]);
        }

        // Выполняем запрос к базе данных
        $db->setQuery($query);
        $db->execute();
    }

    // Функция проверки числа по заданному идентификатору
    protected function checkNumber($id)
    {
        // Получаем объект базы данных Joomla
        $db = JFactory::getDbo();

        // Создаем новый объект запроса
        $query = $db->getQuery(true);

        // Определяем запрос для выборки идентификатора и даты из таблицы сгенерированных чисел
        $query
            ->select("id", "date")
            ->from($db->quoteName("#__generated_numbers"))
            ->where($db->quoteName("id") . " = " . $db->quote($id))
            ->where("DATEDIFF(NOW(), date) >= 1");

        // Выполняем запрос к базе данных
        $db->setQuery($query);
        $db->setQuery($query);

        // с empty какие-то проблемы
        // Возвращаем true, если результат пустой, иначе false
        return !boolval($db->loadResult());
    }

    // Функция получения числа из базы данных по заданному идентификатору
    protected function getNumber($id)
    {
        // Получаем объект базы данных Joomla
        $db = JFactory::getDbo();

        // Создаем новый объект запроса
        $query = $db->getQuery(true);

        // Определяем запрос для выборки числа из таблицы сгенерированных чисел по заданному идентификатору
        $query
            ->select($db->quoteName("number"))
            ->from($db->quoteName("#__generated_numbers"))
            ->where($db->quoteName("id") . " = " . $db->quote($id));

        // Выполняем запрос к базе данных и получаем результат
        $db->setQuery($query);
        return $db->loadResult();
    }
}
