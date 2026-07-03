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
                    node.type,
                    nfd.status,
                    nfd.title,
                    nfd.promote,
                    body.body_value,
                    entit.field_title_in_english_value,
                    summa.field_summary_in_english_value,
                    peni.field_pelin_nimi_value,
                    score.field_arvosana_value
                FROM
                    node
                    LEFT JOIN node__body AS body ON node.nid = body.entity_id AND node.vid = body.revision_id
                    LEFT JOIN node__field_summary_in_english AS summa ON node.nid = summa.entity_id AND node.vid = summa.revision_id
                    LEFT JOIN node__field_title_in_english AS entit ON node.nid = entit.entity_id AND node.vid = entit.revision_id
                    LEFT JOIN node__field_pelin_nimi AS peni ON node.nid = peni.entity_id AND node.vid = peni.revision_id
                    LEFT JOIN node__field_arvosana AS score ON node.nid = score.entity_id AND node.vid = score.revision_id
                    LEFT JOIN node_field_data AS nfd ON node.nid = nfd.nid
                    AND node.vid = nfd.vid
                ORDER BY
                    nid DESC
                LIMIT
                    500;
                """

                with connection.cursor() as cursor:
                    print(f"Executing SQL: {query}")
                    cursor.execute(query)
                    results = cursor.fetchall()
                    print(f"Successfully retrieved {len(results)} rows.")

                    serializable_results = serialize_data(results)

                nodes = {}
                hours_ago = 0
                for res in serializable_results:
                    # Random creation time
                    hours_ago += randint(1,10)
                    created = datetime.datetime.now() - datetime.timedelta(hours=hours_ago)

                    # Random platforms, 0 to 3 values from range 1..7
                    alustat = sorted(choices(range(1,8), k=randint(0,3)))

                    # Random games, 1 to 5 values from range 1..10
                    pelit = sorted(choices(range(1,11), k=randint(1,5)))

                    # Random people, 0 to 3 values from range 1..6
                    ihmiset = sorted(choices(range(1,7), k=randint(0,3)))

                    # Random publishers, 0 to 2 values from range 1..6
                    julkaisijat = sorted(choices(range(1,7), k=randint(0,2)))

                    # Random studios, 0 to 2 values from range 1..7
                    studiot = sorted(choices(range(1,8), k=randint(0,2)))

                    # Random images, 0 to 5 values from range 1..27
                    kuvat = sorted(choices(range(1,28), k=randint(0,5)))

                    # Random series, 5% chance of selecting a single series from range 1..4
                    sarja = choices(range(1,5), k=1)[0] if randint(1,20) == 1 else None

                    # Replace all img tags and earlier drupal-media tags with a new drupal-media tag with a random image uuid
                    body = res['body_value'] if res['body_value'] else ''
                    parts = re.split(r"<img[^>]*>|<drupal-media.*drupal-media>", body)
                    if len(parts) > 1:
                        iterator = iter(parts)
                        body = next(iterator)
                        for part in iterator:
                            body += f'<drupal-media data-entity-type="media" data-entity-uuid="{choice(image_uuids)}">&nbsp;</drupal-media>'
                            body += part

                    if res['type'] not in nodes:
                        nodes[res['type']] = []

                    nodes[res['type']].append({
                        "id": res['nid'],
                        "title": res['title'],
                        "body": body,
                        "uid": randint(2, 6),
                        "status": res['status'],
                        "promote": res['promote'],
                        "created": created.strftime("%Y-%m-%d %H:%M:%S"),
                        "hero_image_id": randint(1,27),
                        "alustat_ids": alustat,
                        "pelit_ids": pelit,
                        "ihmiset_ids": ihmiset,
                        "julkaisijat_ids": julkaisijat,
                        "studiot_ids": studiot,
                        "kuvat_ids": kuvat,
                        "sarja_id": sarja,
                        "title_english": res['field_title_in_english_value'],
                        "summary_english": res['field_summary_in_english_value'],
                        "pelin_nimi": res['field_pelin_nimi_value'],
                        "arvosana": res['field_arvosana_value']
                    })

                for t in nodes:
                    filename = t + '.json'
                    json.dump(nodes[t], open(filename, 'w'), indent=2, ensure_ascii=False)

            finally:
                connection.close()

    except Exception as e:
        print(f"\nAn error occurred: {e}")
        print("Please check your SSH and Database credentials/connectivity.")


if __name__ == "__main__":
    main()
