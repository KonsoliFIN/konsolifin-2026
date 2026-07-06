#!/usr/bin/env python3
"""
retrieve.py

A script to read data from a MariaDB database over an SSH tunnel.
Typically used to pull content/data for Drupal migrations.

Requirements:
    pip install sshtunnel pymysql

Usage:
    # 1. Edit the configuration below or set environment variables.
    # 2. Run the script:
    #    python retrieve.py --query "SELECT * FROM node LIMIT 5" --output articles.json
"""

from dotenv import parser
import dotenv
import os
import json
import datetime
import re
from random import randint, choices, choice
from sshtunnel import SSHTunnelForwarder
import pymysql

dotenv.load_dotenv()

# Configuration Defaults (can be overridden by Environment Variables or edited here)
SSH_HOST = os.environ.get("SSH_HOST")
SSH_PORT = int(os.environ.get("SSH_PORT"))
SSH_USER = os.environ.get("SSH_USER")

DB_HOST = os.environ.get("DB_HOST")
DB_PORT = int(os.environ.get("DB_PORT"))
DB_USER = os.environ.get("DB_USER")
DB_PASSWORD = os.environ.get("DB_PASSWORD")
DB_NAME = os.environ.get("DB_NAME")


def serialize_data(data):
    """
    Recursively converts non-serializable objects (like datetime) to string representations
    so they can be cleanly dumped into JSON.
    """
    if isinstance(data, list):
        return [serialize_data(item) for item in data]
    elif isinstance(data, dict):
        return {k: serialize_data(v) for k, v in data.items()}
    elif isinstance(data, (datetime.datetime, datetime.date)):
        return data.isoformat()
    return data


def main():
    # Read image UUIDs from local database
    query = """
    SELECT
        uuid
    FROM
        media
    WHERE
        bundle = 'image';
    """
    connection = pymysql.connect(
        host="db",
        user='user',
        password='password',
        database='drupal',
        cursorclass=pymysql.cursors.DictCursor
    )

    with connection.cursor() as cursor:
        print(f"Executing SQL: {query}")
        cursor.execute(query)
        results = cursor.fetchall()
        print(f"Successfully retrieved {len(results)} rows.")

        image_uuids = [row['uuid'] for row in serialize_data(results)]

    print(image_uuids)
    # Determine SSH authentication method
    ssh_auth = {}
    print(f"Connecting to SSH host {SSH_HOST}:{SSH_PORT} as user '{SSH_USER}'...")

    try:
        # Establish the SSH tunnel
        # We tunnel a local port (dynamically allocated) to the remote database host and port
        with SSHTunnelForwarder(
            (SSH_HOST, SSH_PORT),
            ssh_username=SSH_USER,
            remote_bind_address=(DB_HOST, DB_PORT),
            **ssh_auth
        ) as tunnel:

            print(f"SSH Tunnel established. Local bind port: {tunnel.local_bind_port}")
            print(f"Connecting to MariaDB on 127.0.0.1:{tunnel.local_bind_port} (database: {DB_NAME})...")

            # Connect to the DB using the tunnel's local port
            connection = pymysql.connect(
                host="127.0.0.1",
                port=tunnel.local_bind_port,
                user=DB_USER,
                password=DB_PASSWORD,
                database=DB_NAME,
                cursorclass=pymysql.cursors.DictCursor
            )

            try:
                query = """
                SELECT
                    node.nid,
                    nfd.title,
                    body.body_value as body,
                    body.body_summary as summary,
                    node.type,
                    nfd.uid,
                    nfd.status,
                    nfd.promote,
                    nfd.created,
                    t_alustat.alustat as alustat_ids,
                    t_pelit.pelit as pelit_ids,
                    t_ihmiset.ihmiset as ihmiset_ids,
                    t_julkaisijat.julkaisijat as julkaisijat_ids,
                    t_studiot.studiot as studiot_ids,
                    entit.field_title_in_english_value as title_english,
                    summa.field_summary_in_english_value as summary_english,
                    peni.field_pelin_nimi_value as pelin_nimi,
                    score.field_arvosana_value as arvosana,
                    julk_aika.field_julkaisuajankohta_accuracy_level as publish_accuracy,
                    julk_aika.field_julkaisuajankohta_stored_date as publish_date,
                    julk_tyyppi.field_tyyppi_value as publish_type
                FROM
                    node
                    LEFT JOIN node__body AS body ON node.nid = body.entity_id AND node.vid = body.revision_id
                    LEFT JOIN node__field_summary_in_english AS summa ON node.nid = summa.entity_id AND node.vid = summa.revision_id
                    LEFT JOIN node__field_title_in_english AS entit ON node.nid = entit.entity_id AND node.vid = entit.revision_id
                    LEFT JOIN node__field_pelin_nimi AS peni ON node.nid = peni.entity_id AND node.vid = peni.revision_id
                    LEFT JOIN node__field_arvosana AS score ON node.nid = score.entity_id AND node.vid = score.revision_id
                    LEFT JOIN node__field_arvosteltu_versio AS arvostelualusta ON node.nid = arvostelualusta.entity_id AND node.vid = arvostelualusta.revision_id
                    LEFT JOIN node__field_julkaisuajankohta AS julk_aika ON node.nid = julk_aika.entity_id AND node.vid = julk_aika.revision_id
                    LEFT JOIN node__field_tyyppi AS julk_tyyppi ON node.nid = julk_tyyppi.entity_id AND node.vid = julk_tyyppi.revision_id
                    LEFT JOIN node_field_data AS nfd ON node.nid = nfd.nid AND node.vid = nfd.vid
                    LEFT JOIN (
                        SELECT
                            entity_id AS nid,
                            revision_id AS vid,
                            GROUP_CONCAT(field_pelit_target_id) AS pelit
                        FROM
                            node__field_pelit
                        GROUP BY
                            nid,
                            vid
                    ) AS t_pelit ON node.nid = t_pelit.nid AND node.vid = t_pelit.vid
                    LEFT JOIN (
                        SELECT
                            entity_id AS nid,
                            revision_id AS vid,
                            GROUP_CONCAT(field_ihmiset_target_id) AS ihmiset
                        FROM
                            node__field_ihmiset
                        GROUP BY
                            nid,
                            vid
                    ) AS t_ihmiset ON node.nid = t_ihmiset.nid AND node.vid = t_ihmiset.vid
                    LEFT JOIN (
                        SELECT
                            entity_id AS nid,
                            revision_id AS vid,
                            GROUP_CONCAT(field_studiot_target_id) AS studiot
                        FROM
                            node__field_studiot
                        GROUP BY
                            nid,
                            vid
                    ) AS t_studiot ON node.nid = t_studiot.nid AND node.vid = t_studiot.vid
                    LEFT JOIN (
                        SELECT
                            entity_id AS nid,
                            revision_id AS vid,
                            GROUP_CONCAT(field_julkaisijat_target_id) AS julkaisijat
                        FROM
                            node__field_julkaisijat
                        GROUP BY
                            nid,
                            vid
                    ) AS t_julkaisijat ON node.nid = t_julkaisijat.nid AND node.vid = t_julkaisijat.vid
                    LEFT JOIN (
                        SELECT
                            entity_id AS nid,
                            revision_id AS vid,
                            GROUP_CONCAT(field_alustat_target_id) AS alustat
                        FROM
                            node__field_alustat
                        GROUP BY
                            nid,
                            vid
                    ) AS t_alustat ON node.nid = t_alustat.nid AND node.vid = t_alustat.vid
                ORDER BY
                    nid DESC
                LIMIT
                    100;
                """

                with connection.cursor() as cursor:
                    print(f"Executing SQL: {query}")
                    cursor.execute(query)
                    results = cursor.fetchall()
                    print(f"Successfully retrieved {len(results)} rows.")

                    serializable_results = serialize_data(results)

                nodes = {}
                terms = set()
                writers = set()
                for res in serializable_results:
                    # Random images, 0 to 5 values from range 1..27
                    kuvat = sorted(choices(range(1,28), k=randint(0,5)))
                    hero_id = choice(range(1,28))

                    # Replace all img tags and earlier drupal-media tags with a new drupal-media tag with a random image uuid
                    body = res['body'] if res['body'] else ''
                    parts = re.split(r"<img[^>]*>|<drupal-media.*drupal-media>", body)
                    if len(parts) > 1:
                        iterator = iter(parts)
                        body = next(iterator)
                        for part in iterator:
                            body += f'<drupal-media data-entity-type="media" data-entity-uuid="{choice(image_uuids)}">&nbsp;</drupal-media>'
                            body += part

                    res['body'] = body

                    # Convert comma-separated strings to lists of integers, filtering out any None or empty strings
                    res['pelit_ids'] = [int(x) for x in res['pelit_ids'].split(',')] if res['pelit_ids'] else []
                    res['ihmiset_ids'] = [int(x) for x in res['ihmiset_ids'].split(',')] if res['ihmiset_ids'] else []
                    res['julkaisijat_ids'] = [int(x) for x in res['julkaisijat_ids'].split(',')] if res['julkaisijat_ids'] else []
                    res['studiot_ids'] = [int(x) for x in res['studiot_ids'].split(',')] if res['studiot_ids'] else []
                    res['alustat_ids'] = [int(x) for x in res['alustat_ids'].split(',')] if res['alustat_ids'] else []

                    terms.update(res['pelit_ids'])
                    terms.update(res['ihmiset_ids'])
                    terms.update(res['julkaisijat_ids'])
                    terms.update(res['studiot_ids'])
                    terms.update(res['alustat_ids'])

                    writers.update(res['uid'])

                    res['hero_image_id'] = hero_id
                    res['kuvat_ids'] = kuvat

                    if res['type'] not in nodes:
                        nodes[res['type']] = []

                    nodes[res['type']].append(res)

                for t in nodes:
                    filename = "../data/nodes/" +t + '.json'
                    json.dump(nodes[t], open(filename, 'w'), indent=2, ensure_ascii=False)

            finally:
                connection.close()

    except Exception as e:
        print(f"\nAn error occurred: {e}")
        print("Please check your SSH and Database credentials/connectivity.")


if __name__ == "__main__":
    main()
